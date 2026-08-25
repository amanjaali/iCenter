<?php

namespace App\Services\Partners;

use App\Enums\JournalSource;
use App\Enums\RevenueShareBasis;
use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\Period;
use App\Models\Project;
use App\Models\RevenueShareRun;
use App\Models\RevenueSharePayment;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §5.1 — the 50/50 split with Cyber Gate.
 *
 * §10.1 leaves open whether the 50% is taken from gross revenue (with expenses
 * then coming out of ALLVA's half) or from net profit after expenses. Both are
 * implemented here; the setting picks one and the choice is stamped on each run
 * so a posted period is never restated by a later change of mind.
 *
 * Posting, either way:
 *   DR 5010 Cyber Gate revenue share   CR 2040 Cyber Gate payable
 * Paying it:
 *   DR 2040 Cyber Gate payable         CR 1020 Bank
 */
class RevenueShareService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly JournalService $journals,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Work out the share for a period without writing anything.
     *
     * @return array{
     *   basis: RevenueShareBasis, percent: float, gross_revenue: float,
     *   operating_expenses: float, revenue_base: float, share_amount: float,
     *   allva_share: float
     * }
     */
    public function calculate(Period $period, ?Project $project = null, ?RevenueShareBasis $basis = null): array
    {
        $basis ??= $this->settings->revenueShareBasis();
        $percent = $this->settings->revenueSharePercent();

        $filters = $project ? ['project_id' => $project->id] : [];
        $from = $period->start_date;
        $to = $period->end_date;

        $grossRevenue = $this->ledger->classTotal([4000], $from, $to, $filters);

        // Class 5000 minus the share itself would be circular, so the base for
        // the "net" treatment uses operating expenses (6000-9000) plus any
        // direct cost that is not the share.
        $operatingExpenses = $this->ledger->classTotal([6000, 7000, 8000, 9000], $from, $to, $filters);
        $otherDirectCosts = $this->otherDirectCosts($from, $to, $filters);

        $revenueBase = $basis === RevenueShareBasis::Gross
            ? $grossRevenue
            : round($grossRevenue - $operatingExpenses - $otherDirectCosts, 2);

        // A loss produces no share to pay: the partner does not fund losses.
        $revenueBase = max(0, $revenueBase);
        $shareAmount = Money::round($revenueBase * $percent / 100);

        return [
            'basis' => $basis,
            'percent' => $percent,
            'gross_revenue' => $grossRevenue,
            'operating_expenses' => round($operatingExpenses + $otherDirectCosts, 2),
            'revenue_base' => $revenueBase,
            'share_amount' => $shareAmount,
            'allva_share' => Money::round($revenueBase - $shareAmount),
        ];
    }

    /** Create (or refresh) the draft run for a period. */
    public function prepare(Period $period, ?Project $project = null): RevenueShareRun
    {
        $calculation = $this->calculate($period, $project);

        $run = RevenueShareRun::firstOrNew([
            'period_id' => $period->id,
            'project_id' => $project?->id,
        ]);

        if ($run->exists && ! $run->isDraft()) {
            return $run;
        }

        $run->fill([
            'reference' => $run->reference ?? DocumentSequence::next('revenue_share', (int) $period->start_date->year),
            'partner_name' => $this->settings->revenueSharePartnerName(),
            'basis' => $calculation['basis'],
            'share_percent' => $calculation['percent'],
            'gross_revenue' => $calculation['gross_revenue'],
            'operating_expenses' => $calculation['operating_expenses'],
            'revenue_base' => $calculation['revenue_base'],
            'share_amount' => $calculation['share_amount'],
            'status' => 'draft',
            'created_by' => $run->created_by ?? Auth::id(),
        ])->save();

        return $run->refresh();
    }

    /** Post the share as a direct cost and a payable. */
    public function post(RevenueShareRun $run): RevenueShareRun
    {
        if (! $run->isDraft()) {
            throw new PostingException("Revenue share {$run->reference} has already been posted.");
        }

        if (Money::isZero((float) $run->share_amount)) {
            throw new PostingException("Revenue share {$run->reference} is zero — there is nothing to post.");
        }

        return DB::transaction(function () use ($run) {
            $run->loadMissing('period', 'project');
            $postingDate = $run->period->isOpen() ? $run->period->end_date : Carbon::today();

            $journal = $this->journals->post(
                $postingDate,
                "{$run->partner_name} revenue share — {$run->period->label()}",
                [
                    [
                        'account' => Account::system('revenue_share_cost'),
                        'description' => "{$run->partner_name} {$run->share_percent}% of "
                            .($run->basis === RevenueShareBasis::Gross ? 'gross revenue' : 'net profit')
                            .' — '.$run->period->label(),
                        'debit' => (float) $run->share_amount,
                        'project_id' => $run->project_id ?? Project::default()?->id,
                    ],
                    [
                        'account' => Account::system('revenue_share_payable'),
                        'description' => "Payable to {$run->partner_name} — {$run->period->label()}",
                        'credit' => (float) $run->share_amount,
                    ],
                ],
                JournalSource::RevenueShare,
                $run,
            );

            $run->forceFill([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ])->save();

            $this->audit->record(
                'revenue_share_posted',
                $run,
                newValues: [
                    'basis' => $run->basis->value,
                    'base' => (float) $run->revenue_base,
                    'share' => (float) $run->share_amount,
                ],
                description: "Posted {$run->partner_name} revenue share for {$run->period->label()}",
            );

            return $run->refresh();
        });
    }

    /** Settle part or all of the payable. */
    public function pay(
        RevenueShareRun $run,
        Carbon $date,
        float $amount,
        Account $sourceAccount,
        string $method = 'bank',
        ?string $reference = null,
    ): RevenueSharePayment {
        $amount = Money::round($amount);

        if ($run->status === 'draft') {
            throw new PostingException("Post the revenue share {$run->reference} before paying it.");
        }

        if ($amount <= 0 || $amount > $run->outstandingAmount() + 0.005) {
            throw new PostingException(
                'The payment must be between zero and the outstanding '.Money::format($run->outstandingAmount()).'.'
            );
        }

        return DB::transaction(function () use ($run, $date, $amount, $sourceAccount, $method, $reference) {
            $payment = RevenueSharePayment::create([
                'revenue_share_run_id' => $run->id,
                'payment_date' => $date,
                'amount' => $amount,
                'method' => $method,
                'source_account_id' => $sourceAccount->id,
                'reference' => $reference,
                'created_by' => Auth::id(),
            ]);

            $journal = $this->journals->post(
                $date,
                "Payment to {$run->partner_name} — {$run->reference}",
                [
                    [
                        'account' => Account::system('revenue_share_payable'),
                        'description' => "Settlement of {$run->partner_name} share",
                        'debit' => $amount,
                    ],
                    [
                        'account' => $sourceAccount,
                        'description' => "Paid to {$run->partner_name}".($reference ? " (ref {$reference})" : ''),
                        'credit' => $amount,
                    ],
                ],
                JournalSource::RevenueSharePayment,
                $payment,
            );

            $payment->update(['journal_id' => $journal->id]);

            $paid = Money::round((float) $run->payments()->sum('amount'));

            $run->forceFill([
                'paid_amount' => $paid,
                'status' => $paid >= (float) $run->share_amount - 0.005 ? 'settled' : 'posted',
            ])->save();

            $this->audit->record(
                'revenue_share_paid',
                $run,
                newValues: ['amount' => $amount, 'journal' => $journal->reference],
                description: "Paid {$run->partner_name} ".Money::format($amount, true),
            );

            return $payment->refresh();
        });
    }

    /**
     * Direct costs other than the revenue share itself. Needed so the "net"
     * basis does not try to subtract the share from the base it is computed on.
     */
    private function otherDirectCosts(Carbon $from, Carbon $to, array $filters): float
    {
        $shareAccountId = Account::system('revenue_share_cost')->id;

        $row = $this->ledger->lines($from, $to, $filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('a.class', 5000)
            ->where('jl.account_id', '!=', $shareAccountId)
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        return round((float) ($row->debit ?? 0) - (float) ($row->credit ?? 0), 2);
    }
}
