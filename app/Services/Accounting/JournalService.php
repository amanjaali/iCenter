<?php

namespace App\Services\Accounting;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\Period;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The posting engine. Everything that touches the general ledger goes through
 * here, so the rules the scope of work insists on live in one place:
 *
 *  - a journal must balance;
 *  - it cannot enter a period that is not open;
 *  - an expense line must carry a cost centre and a project (§7);
 *  - nothing is ever deleted once posted — corrections are reversals (§9.3).
 */
class JournalService
{
    public function __construct(
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Build a journal and post it in one step. This is what every automatic
     * posting (invoice, payroll, depreciation, payout) calls.
     *
     * @param  array<int,array<string,mixed>>  $lines  each with account (code, id or Account), debit/credit and optional tags
     */
    public function post(
        Carbon|string $date,
        string $memo,
        array $lines,
        JournalSource $source = JournalSource::Manual,
        ?Model $sourceDocument = null,
        ?string $reference = null,
    ): Journal {
        return DB::transaction(function () use ($date, $memo, $lines, $source, $sourceDocument, $reference) {
            $journal = $this->createDraft($date, $memo, $lines, $source, $sourceDocument, $reference);

            return $this->postDraft($journal);
        });
    }

    /**
     * Create an unposted journal. Draft journals are validated for structure
     * but not for period state — the period is only enforced at posting.
     *
     * @param  array<int,array<string,mixed>>  $lines
     */
    public function createDraft(
        Carbon|string $date,
        string $memo,
        array $lines,
        JournalSource $source = JournalSource::Manual,
        ?Model $sourceDocument = null,
        ?string $reference = null,
    ): Journal {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return DB::transaction(function () use ($date, $memo, $lines, $source, $sourceDocument, $reference) {
            $prepared = $this->prepareLines($lines);

            [$totalDebit, $totalCredit] = $this->totals($prepared);

            if (! Money::equals($totalDebit, $totalCredit)) {
                throw PostingException::unbalanced($totalDebit, $totalCredit);
            }

            $period = $this->periods->resolveFor($date);

            $journal = Journal::create([
                'reference' => $reference ?? DocumentSequence::next('journal', (int) $date->year),
                'journal_date' => $date,
                'period_id' => $period->id,
                'memo' => $memo,
                'source_type' => $source,
                'source_id' => $sourceDocument?->getKey(),
                'status' => JournalStatus::Draft,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => Auth::id(),
            ]);

            foreach ($prepared as $index => $line) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'department_id' => $line['department_id'],
                    'project_id' => $line['project_id'],
                    'partner_id' => $line['partner_id'],
                    'bus_company_id' => $line['bus_company_id'],
                    'supplier_id' => $line['supplier_id'],
                    'employee_id' => $line['employee_id'],
                    'nature' => $line['nature'],
                    'treatment' => $line['treatment'],
                ]);
            }

            return $journal->load('lines.account');
        });
    }

    /** Move a draft journal into the ledger. */
    public function postDraft(Journal $journal): Journal
    {
        if ($journal->isPosted()) {
            throw PostingException::alreadyPosted($journal->reference);
        }

        if ($journal->isReversed()) {
            throw new PostingException("Journal {$journal->reference} is reversed and cannot be posted.");
        }

        $journal->loadMissing('lines');

        if ($journal->lines->count() < 2) {
            throw PostingException::emptyJournal();
        }

        // Read fresh: a draft may have been created while its month was open
        // and be posted after month end has closed it.
        $period = Period::find($journal->period_id);

        if (! $period?->isOpen()) {
            throw PostingException::periodClosed($period?->code ?? (string) $journal->period_id);
        }

        if (! $journal->isBalanced()) {
            throw PostingException::unbalanced((float) $journal->total_debit, (float) $journal->total_credit);
        }

        $journal->update([
            'status' => JournalStatus::Posted,
            'posted_by' => Auth::id(),
            'posted_at' => now(),
        ]);

        $this->audit->record(
            'posted',
            $journal,
            newValues: [
                'reference' => $journal->reference,
                'date' => $journal->journal_date->toDateString(),
                'total' => (float) $journal->total_debit,
            ],
            description: "Posted journal {$journal->reference}",
        );

        return $journal->refresh();
    }

    /**
     * Scope of work §9.3 — "Corrections are made by reversing entries, not by
     * deletion." The reversal is a new journal with the sides swapped, dated
     * either on the original date or on the correction date if the original
     * month has since been closed.
     */
    public function reverse(Journal $journal, string $reason, Carbon|string|null $date = null): Journal
    {
        if (! $journal->canBeReversed()) {
            throw $journal->isPosted()
                ? PostingException::alreadyReversed($journal->reference)
                : new PostingException("Only a posted journal can be reversed; {$journal->reference} is {$journal->status->value}.");
        }

        return DB::transaction(function () use ($journal, $reason, $date) {
            $journal->loadMissing('lines');

            // Read the period straight from the database rather than through
            // the relation: the journal may have been loaded while its month
            // was still open and closed since.
            $originalPeriod = Period::find($journal->period_id);

            // Reverse in the original month when it is still open, otherwise in
            // the current one — never force a closed period back open.
            $reversalDate = $date
                ? ($date instanceof Carbon ? $date : Carbon::parse($date))
                : ($originalPeriod?->isOpen() ? $journal->journal_date : Carbon::today());

            $lines = $journal->lines->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'description' => 'Reversal — '.($line->description ?? ''),
                // The swap: what was debited is credited and the other way round.
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'department_id' => $line->department_id,
                'project_id' => $line->project_id,
                'partner_id' => $line->partner_id,
                'bus_company_id' => $line->bus_company_id,
                'supplier_id' => $line->supplier_id,
                'employee_id' => $line->employee_id,
                'nature' => $line->nature?->value,
                'treatment' => $line->treatment?->value,
            ])->all();

            $reversal = $this->createDraft(
                $reversalDate,
                "Reversal of {$journal->reference} — {$reason}",
                $lines,
                JournalSource::Reversal,
                $journal,
            );

            $reversal->update(['reversal_of_id' => $journal->id, 'reversal_reason' => $reason]);
            $reversal = $this->postDraft($reversal);

            $journal->update([
                'status' => JournalStatus::Reversed,
                'reversed_by_id' => $reversal->id,
                'reversal_reason' => $reason,
            ]);

            $this->audit->record(
                'reversed',
                $journal,
                description: "Reversed journal {$journal->reference} with {$reversal->reference}",
                reason: $reason,
            );

            return $reversal;
        });
    }

    /**
     * Delete a draft journal. Posted journals are never deletable — the method
     * refuses rather than cascading.
     */
    public function deleteDraft(Journal $journal): void
    {
        if (! $journal->isDraft()) {
            throw new PostingException(
                "Journal {$journal->reference} is {$journal->status->value} and cannot be deleted. Reverse it instead."
            );
        }

        $reference = $journal->reference;
        $journal->delete();

        $this->audit->record('deleted', null, description: "Deleted draft journal {$reference}");
    }

    /**
     * Normalise and validate incoming line data.
     *
     * @param  array<int,array<string,mixed>>  $lines
     * @return array<int,array<string,mixed>>
     */
    private function prepareLines(array $lines): array
    {
        $prepared = [];

        foreach (array_values($lines) as $index => $line) {
            $lineNo = $index + 1;
            $account = $this->resolveAccount($line['account'] ?? $line['account_id'] ?? null, $lineNo);

            if (! $account->is_postable) {
                throw PostingException::notPostable($account->displayName());
            }

            $debit = Money::round($line['debit'] ?? 0);
            $credit = Money::round($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                // A negative debit is an ambiguous way of writing a credit;
                // insisting on the correct side keeps the ledger readable.
                throw new PostingException("Line {$lineNo} has a negative amount. Put the value on the other side instead.");
            }

            if ($debit > 0 && $credit > 0) {
                throw PostingException::bothSides($lineNo);
            }

            if (Money::isZero($debit) && Money::isZero($credit)) {
                throw PostingException::zeroLine($lineNo);
            }

            $departmentId = $line['department_id'] ?? null;
            $projectId = $line['project_id'] ?? null;

            if ($account->requires_department && ! $departmentId) {
                throw PostingException::missingTag($account->displayName(), 'cost centre');
            }

            if ($account->requires_project && ! $projectId) {
                throw PostingException::missingTag($account->displayName(), 'project');
            }

            $prepared[] = [
                'account_id' => $account->id,
                'description' => isset($line['description']) ? substr((string) $line['description'], 0, 500) : null,
                'debit' => $debit,
                'credit' => $credit,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'partner_id' => $line['partner_id'] ?? null,
                'bus_company_id' => $line['bus_company_id'] ?? null,
                'supplier_id' => $line['supplier_id'] ?? null,
                'employee_id' => $line['employee_id'] ?? null,
                'nature' => $this->enumValue($line['nature'] ?? null),
                'treatment' => $this->enumValue($line['treatment'] ?? null),
            ];
        }

        if (count($prepared) < 2) {
            throw PostingException::emptyJournal();
        }

        return $prepared;
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? $value->value : ($value === null ? null : (string) $value);
    }

    /** Accept an Account, an id, or an account code — services use all three. */
    private function resolveAccount(mixed $account, int $lineNo): Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        if (is_int($account) || (is_string($account) && ctype_digit($account) && strlen($account) > 4)) {
            return Account::findOrFail($account);
        }

        if (is_string($account) && $account !== '') {
            return Account::byCode($account);
        }

        throw new PostingException("Line {$lineNo} has no account.");
    }

    /** @return array{0: float, 1: float} */
    private function totals(array $lines): array
    {
        return [
            Money::round(array_sum(array_column($lines, 'debit'))),
            Money::round(array_sum(array_column($lines, 'credit'))),
        ];
    }
}
