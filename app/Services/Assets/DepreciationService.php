<?php

namespace App\Services\Assets;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\DepreciationLine;
use App\Models\DepreciationRun;
use App\Models\DocumentSequence;
use App\Models\FixedAsset;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §6.8 (8050 vehicle depreciation), §6.9 (9060 depreciation of
 * other assets, 9070 amortisation) and §7 (capex is depreciated rather than
 * charged in the period).
 *
 * Posting a month:
 *   DR 8050 / 9060 / 9070 depreciation expense
 *     CR 1190 Accumulated depreciation
 */
class DepreciationService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /** Build the draft schedule for a month. */
    public function prepare(Carbon $month): DepreciationRun
    {
        $month = $month->copy()->startOfMonth();

        if ($existing = DepreciationRun::whereDate('run_month', $month)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($month) {
            $run = DepreciationRun::create([
                'reference' => DocumentSequence::next('depreciation', (int) $month->year),
                'period_id' => $this->periods->resolveFor($month->copy()->endOfMonth())->id,
                'run_month' => $month,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $total = 0.0;

            $assets = FixedAsset::active()
                ->whereDate('depreciation_start_date', '<=', $month->copy()->endOfMonth())
                ->orderBy('code')
                ->get();

            foreach ($assets as $asset) {
                $charge = $asset->chargeFor($month);

                if ($charge <= 0) {
                    continue;
                }

                $accumulated = Money::round((float) $asset->accumulated_depreciation + $charge);

                DepreciationLine::create([
                    'depreciation_run_id' => $run->id,
                    'fixed_asset_id' => $asset->id,
                    'amount' => $charge,
                    'accumulated_after' => $accumulated,
                    'net_book_value_after' => Money::round((float) $asset->cost - $accumulated),
                ]);

                $total += $charge;
            }

            $run->update(['total_amount' => Money::round($total)]);

            return $run->fresh('lines.asset');
        });
    }

    /** Post the month's charge and roll the accumulated figures forward. */
    public function post(DepreciationRun $run): DepreciationRun
    {
        if (! $run->isDraft()) {
            throw new PostingException("Depreciation run {$run->reference} has already been posted.");
        }

        $run->loadMissing('lines.asset');

        if ($run->lines->isEmpty()) {
            throw new PostingException("Depreciation run {$run->reference} has nothing to charge.");
        }

        return DB::transaction(function () use ($run) {
            $postingDate = $run->run_month->copy()->endOfMonth();
            $journalLines = [];

            // Group the debits by expense account and cost centre so vehicle
            // depreciation lands in 8050 and everything else in 9060 / 9070.
            $grouped = $run->lines->groupBy(fn (DepreciationLine $line) => implode('|', [
                $line->asset->depreciation_expense_account_id,
                $line->asset->department_id ?? 0,
                $line->asset->project_id ?? 0,
            ]));

            foreach ($grouped as $key => $group) {
                [$accountId, $departmentId, $projectId] = array_map('intval', explode('|', $key));
                $amount = Money::round($group->sum('amount'));

                if ($amount <= 0) {
                    continue;
                }

                $journalLines[] = [
                    'account_id' => $accountId,
                    'description' => 'Depreciation — '.$run->run_month->format('F Y'),
                    'debit' => $amount,
                    'department_id' => $departmentId ?: null,
                    'project_id' => $projectId ?: null,
                    'nature' => 'fixed',
                    'treatment' => 'opex',
                ];
            }

            $journalLines[] = [
                'account' => Account::system('accumulated_depreciation'),
                'description' => 'Accumulated depreciation — '.$run->run_month->format('F Y'),
                'credit' => Money::round($run->lines->sum('amount')),
            ];

            $journal = $this->journals->post(
                $postingDate,
                "Depreciation {$run->reference} — {$run->run_month->format('F Y')}",
                $journalLines,
                JournalSource::Depreciation,
                $run,
            );

            foreach ($run->lines as $line) {
                $asset = $line->asset;

                $asset->forceFill([
                    'accumulated_depreciation' => (float) $line->accumulated_after,
                    'status' => (float) $line->net_book_value_after <= (float) $asset->salvage_value + 0.005
                        ? 'fully_depreciated'
                        : $asset->status,
                ])->save();
            }

            $run->forceFill([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ])->save();

            $this->audit->record(
                'depreciation_posted',
                $run,
                newValues: ['assets' => $run->lines->count(), 'total' => (float) $run->total_amount],
                description: "Posted depreciation for {$run->run_month->format('F Y')}",
            );

            return $run->refresh();
        });
    }

    /**
     * Dispose of an asset: clear its cost and accumulated depreciation, and
     * book the gain or loss to 9090 Miscellaneous.
     */
    public function dispose(FixedAsset $asset, Carbon $date, float $proceeds, Account $proceedsAccount, string $reason): FixedAsset
    {
        if ($asset->status === 'disposed') {
            throw new PostingException("{$asset->name} has already been disposed of.");
        }

        return DB::transaction(function () use ($asset, $date, $proceeds, $proceedsAccount, $reason) {
            $proceeds = Money::round($proceeds);
            $netBookValue = $asset->netBookValue();
            $gainOrLoss = Money::round($proceeds - $netBookValue);

            $lines = [];

            if ($proceeds > 0) {
                $lines[] = [
                    'account' => $proceedsAccount,
                    'description' => "Proceeds from disposal of {$asset->name}",
                    'debit' => $proceeds,
                ];
            }

            if ((float) $asset->accumulated_depreciation > 0) {
                $lines[] = [
                    'account_id' => $asset->accumulated_depreciation_account_id,
                    'description' => "Clear accumulated depreciation — {$asset->name}",
                    'debit' => (float) $asset->accumulated_depreciation,
                ];
            }

            // A loss is a debit to expense; a gain is a credit to other income.
            if ($gainOrLoss < 0) {
                $lines[] = [
                    'account' => '9090',
                    'description' => "Loss on disposal of {$asset->name}",
                    'debit' => abs($gainOrLoss),
                    'department_id' => $asset->department_id,
                    'project_id' => $asset->project_id,
                    'nature' => 'variable',
                    'treatment' => 'opex',
                ];
            } elseif ($gainOrLoss > 0) {
                $lines[] = [
                    'account' => '4090',
                    'description' => "Gain on disposal of {$asset->name}",
                    'credit' => $gainOrLoss,
                    'project_id' => $asset->project_id,
                ];
            }

            $lines[] = [
                'account_id' => $asset->asset_account_id,
                'description' => "Remove {$asset->name} from the asset register",
                'credit' => (float) $asset->cost,
            ];

            $journal = $this->journals->post(
                $date,
                "Disposal of {$asset->name} — {$reason}",
                $lines,
                JournalSource::AssetDisposal,
                $asset,
            );

            $asset->forceFill([
                'status' => 'disposed',
                'disposal_date' => $date,
                'disposal_amount' => $proceeds,
                'disposal_journal_id' => $journal->id,
                'notes' => trim(($asset->notes ? $asset->notes."\n" : '')."Disposed: {$reason}"),
            ])->save();

            $this->audit->record(
                'asset_disposed',
                $asset,
                newValues: ['proceeds' => $proceeds, 'net_book_value' => $netBookValue, 'result' => $gainOrLoss],
                description: "Disposed of {$asset->name}",
                reason: $reason,
            );

            return $asset->refresh();
        });
    }
}
