<?php

namespace App\Services\Partners;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\DistributionLine;
use App\Models\DistributionRun;
use App\Models\DocumentSequence;
use App\Models\OwnershipChangeLog;
use App\Models\Partner;
use App\Models\PartnerPayout;
use App\Models\Period;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\LedgerService;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §5.2 — "Within Alva, profit is calculated after all operating
 * expenses have been deducted. The remaining net profit is distributed among
 * the 5 partners, 20% to each."
 *
 * The system records, per partner: profit share earned, amount paid out, and
 * amount still outstanding, and every distribution is tied to the period it
 * relates to so a partner can see which months a payment covers.
 *
 * Posting a declaration:
 *   DR 3060 Partner current accounts   CR 2050 Partner distributions payable
 * Paying a partner:
 *   DR 2050 Partner distributions payable   CR 1020 Bank
 */
class DistributionService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly JournalService $journals,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Net profit available for distribution over a window, and each partner's
     * share of it at their current ownership percentage.
     *
     * @return array{
     *   gross_revenue: float, direct_costs: float, operating_expenses: float,
     *   net_profit: float, shares: Collection<int,array{partner:Partner, percent:float, amount:float}>
     * }
     */
    public function calculate(Carbon $from, Carbon $to, float $retained = 0.0): array
    {
        $summary = $this->ledger->profitSummary($from, $to);

        $distributable = Money::round(max(0, $summary['net_profit'] - max(0, $retained)));
        $partners = Partner::active()->orderBy('sort_order')->orderBy('name')->get();

        // Largest-remainder allocation so the partners' shares add back to the
        // distributable amount exactly — five 20% shares of an odd figure would
        // otherwise leave a stray dinar.
        $amounts = Money::allocate(
            $distributable,
            $partners->mapWithKeys(fn (Partner $p) => [$p->id => (float) $p->ownership_percent])->all(),
        );

        $shares = $partners->map(fn (Partner $p) => [
            'partner' => $p,
            'percent' => (float) $p->ownership_percent,
            'amount' => $amounts[$p->id] ?? 0.0,
        ]);

        return [
            'gross_revenue' => $summary['revenue'],
            'direct_costs' => $summary['direct_costs'],
            'operating_expenses' => $summary['operating_expenses'],
            'net_profit' => $summary['net_profit'],
            'retained' => Money::round(max(0, $retained)),
            'distributable' => $distributable,
            'shares' => $shares,
            'total_percent' => round($partners->sum(fn (Partner $p) => (float) $p->ownership_percent), 4),
        ];
    }

    /** Create a draft distribution for a window. */
    public function prepare(Carbon $from, Carbon $to, float $retained = 0.0, ?string $title = null): DistributionRun
    {
        $calculation = $this->calculate($from, $to, $retained);

        return DB::transaction(function () use ($from, $to, $retained, $title, $calculation) {
            $run = DistributionRun::create([
                'reference' => DocumentSequence::next('distribution', (int) $to->year),
                'title' => $title,
                'period_start' => $from,
                'period_end' => $to,
                'period_id' => Period::forDate($to)?->id,
                'gross_revenue' => $calculation['gross_revenue'],
                'direct_costs' => $calculation['direct_costs'],
                'operating_expenses' => $calculation['operating_expenses'],
                'net_profit' => $calculation['net_profit'],
                'retained_amount' => $calculation['retained'],
                'distributable_amount' => $calculation['distributable'],
                'distributed_amount' => 0,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            foreach ($calculation['shares'] as $share) {
                DistributionLine::create([
                    'distribution_run_id' => $run->id,
                    'partner_id' => $share['partner']->id,
                    // Frozen: a later ownership change does not restate this.
                    'ownership_percent' => $share['percent'],
                    'share_amount' => $share['amount'],
                    'paid_amount' => 0,
                    'outstanding_amount' => $share['amount'],
                ]);
            }

            return $run->fresh('lines.partner');
        });
    }

    /** Post the declaration: it becomes a liability to the partners. */
    public function post(DistributionRun $run): DistributionRun
    {
        if (! $run->isDraft()) {
            throw new PostingException("Distribution {$run->reference} has already been posted.");
        }

        $run->loadMissing('lines.partner');

        if ($run->lines->isEmpty() || Money::isZero((float) $run->distributable_amount)) {
            throw new PostingException("Distribution {$run->reference} has nothing to distribute.");
        }

        return DB::transaction(function () use ($run) {
            $postingDate = $this->postingDate($run);
            $lines = [];

            foreach ($run->lines as $line) {
                if (Money::isZero((float) $line->share_amount)) {
                    continue;
                }

                // Drawings go to 3060 partner current accounts, never to the
                // partner's 3010-3050 capital account: capital is what a
                // partner put in, and a profit distribution must not read as a
                // reduction of it. The partner_id tag keeps each partner's
                // drawings separable within the one control account.
                $lines[] = [
                    'account' => Account::system('partner_current'),
                    'description' => "{$line->partner->name} — {$line->ownership_percent}% of "
                        .Money::format($run->distributable_amount).' — '.$run->periodLabel(),
                    'debit' => (float) $line->share_amount,
                    'partner_id' => $line->partner_id,
                ];

                $lines[] = [
                    'account' => Account::system('partner_distributions_payable'),
                    'description' => "Payable to {$line->partner->name} — {$run->periodLabel()}",
                    'credit' => (float) $line->share_amount,
                    'partner_id' => $line->partner_id,
                ];
            }

            $journal = $this->journals->post(
                $postingDate,
                "Partner distribution {$run->reference} — {$run->periodLabel()}",
                $lines,
                JournalSource::Distribution,
                $run,
            );

            $run->forceFill([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ])->save();

            $this->audit->record(
                'distribution_posted',
                $run,
                newValues: [
                    'net_profit' => (float) $run->net_profit,
                    'distributed' => (float) $run->distributable_amount,
                    'partners' => $run->lines->count(),
                ],
                description: "Posted partner distribution {$run->reference} for {$run->periodLabel()}",
            );

            return $run->refresh('lines.partner');
        });
    }

    /** Pay a partner part or all of what they are owed on a distribution. */
    public function payout(
        DistributionLine $line,
        Carbon $date,
        float $amount,
        Account $sourceAccount,
        string $method = 'bank',
        ?string $reference = null,
    ): PartnerPayout {
        $amount = Money::round($amount);
        $line->loadMissing('partner', 'run');

        if ($line->run->isDraft()) {
            throw new PostingException("Post distribution {$line->run->reference} before paying it out.");
        }

        if ($amount <= 0 || $amount > (float) $line->outstanding_amount + 0.005) {
            throw new PostingException(
                'The payout must be between zero and the outstanding '
                .Money::format((float) $line->outstanding_amount).'.'
            );
        }

        return DB::transaction(function () use ($line, $date, $amount, $sourceAccount, $method, $reference) {
            $payout = PartnerPayout::create([
                'reference' => DocumentSequence::next('payment', (int) $date->year),
                'distribution_line_id' => $line->id,
                'partner_id' => $line->partner_id,
                'payout_date' => $date,
                'amount' => $amount,
                'method' => $method,
                'source_account_id' => $sourceAccount->id,
                'reference_note' => $reference,
                'created_by' => Auth::id(),
            ]);

            $journal = $this->journals->post(
                $date,
                "Partner payout — {$line->partner->name} — {$line->run->periodLabel()}",
                [
                    [
                        'account' => Account::system('partner_distributions_payable'),
                        'description' => "Settlement to {$line->partner->name}",
                        'debit' => $amount,
                        'partner_id' => $line->partner_id,
                    ],
                    [
                        'account' => $sourceAccount,
                        'description' => "Paid to {$line->partner->name}".($reference ? " (ref {$reference})" : ''),
                        'credit' => $amount,
                        'partner_id' => $line->partner_id,
                    ],
                ],
                JournalSource::PartnerPayout,
                $payout,
            );

            $payout->update(['journal_id' => $journal->id]);
            $line->refreshPayoutState();

            $run = $line->run;
            $distributed = Money::round((float) $run->lines()->sum('paid_amount'));

            $run->forceFill([
                'distributed_amount' => $distributed,
                'status' => $distributed >= (float) $run->distributable_amount - 0.005 ? 'settled' : 'posted',
            ])->save();

            $this->audit->record(
                'partner_payout',
                $payout,
                newValues: ['partner' => $line->partner->name, 'amount' => $amount, 'journal' => $journal->reference],
                description: "Paid {$line->partner->name} ".Money::format($amount, true),
            );

            return $payout->refresh();
        });
    }

    /**
     * Change a partner's ownership percentage. Scope of work §2 requires this
     * to be configurable, and §9.3 requires the change to be logged.
     */
    public function changeOwnership(Partner $partner, float $newPercent, Carbon $effectiveDate, string $reason): Partner
    {
        $oldPercent = (float) $partner->ownership_percent;

        if (abs($oldPercent - $newPercent) < 0.00005) {
            return $partner;
        }

        return DB::transaction(function () use ($partner, $oldPercent, $newPercent, $effectiveDate, $reason) {
            $partner->update(['ownership_percent' => $newPercent]);

            OwnershipChangeLog::create([
                'partner_id' => $partner->id,
                'old_percent' => $oldPercent,
                'new_percent' => $newPercent,
                'effective_date' => $effectiveDate,
                'reason' => $reason,
                'user_id' => Auth::id(),
            ]);

            $this->audit->record(
                'ownership_changed',
                $partner,
                oldValues: ['ownership_percent' => $oldPercent],
                newValues: ['ownership_percent' => $newPercent],
                description: "Changed {$partner->name}'s ownership from {$oldPercent}% to {$newPercent}%",
                reason: $reason,
            );

            return $partner->refresh();
        });
    }

    /** Total ownership across active partners — should be 100%. */
    public function totalOwnership(): float
    {
        return round((float) Partner::active()->sum('ownership_percent'), 4);
    }

    private function postingDate(DistributionRun $run): Carbon
    {
        $period = Period::forDate($run->period_end);

        return $period && $period->isOpen() ? $run->period_end : Carbon::today();
    }
}
