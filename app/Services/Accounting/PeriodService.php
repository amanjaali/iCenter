<?php

namespace App\Services\Accounting;

use App\Enums\PeriodStatus;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Period;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeriodService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create a fiscal year and its twelve monthly periods. Idempotent: running
     * it again for an existing year returns that year untouched.
     */
    public function createFiscalYear(int $year): FiscalYear
    {
        return DB::transaction(function () use ($year) {
            $existing = FiscalYear::where('name', (string) $year)->first();

            if ($existing) {
                return $existing;
            }

            [$startDay, $startMonth] = array_map(
                'intval',
                explode('-', config('allva.fiscal_year_start', '01-01'))
            );

            $start = Carbon::create($year, $startMonth, $startDay)->startOfDay();
            $end = $start->copy()->addYear()->subDay()->endOfDay();

            $fiscalYear = FiscalYear::create([
                'name' => (string) $year,
                'start_date' => $start,
                'end_date' => $end,
            ]);

            $cursor = $start->copy()->startOfMonth();

            for ($i = 0; $i < 12; $i++) {
                Period::create([
                    'fiscal_year_id' => $fiscalYear->id,
                    'code' => $cursor->format('Y-m'),
                    'start_date' => $cursor->copy()->startOfMonth(),
                    'end_date' => $cursor->copy()->endOfMonth(),
                    'status' => PeriodStatus::Open,
                ]);

                $cursor->addMonth();
            }

            return $fiscalYear->fresh('periods');
        });
    }

    /**
     * The period a date falls in, creating the fiscal year on demand so that a
     * transaction is never rejected merely because nobody set the year up.
     */
    public function resolveFor(Carbon|string $date): Period
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($period = Period::forDate($date)) {
            return $period;
        }

        $this->createFiscalYear((int) $date->year);

        return Period::forDate($date) ?? throw PostingException::noPeriod($date->toDateString());
    }

    /** The period the user is most likely working in. */
    public function current(): Period
    {
        return $this->resolveFor(Carbon::today());
    }

    /**
     * Close a period. Draft journals block the close — an unposted entry in a
     * month that is being closed is exactly the "lost item" the scope of work
     * exists to prevent.
     *
     * @return array{closed: bool, message: string}
     */
    public function close(Period $period): array
    {
        if (! $period->isOpen()) {
            return ['closed' => false, 'message' => "Period {$period->code} is already {$period->status->value}."];
        }

        $drafts = Journal::where('period_id', $period->id)->where('status', 'draft')->count();

        if ($drafts > 0) {
            return [
                'closed' => false,
                'message' => "{$drafts} draft journal(s) still sit in {$period->label()}. Post or delete them before closing.",
            ];
        }

        $period->update([
            'status' => PeriodStatus::Closed,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        $this->audit->record('period_closed', $period, description: "Closed period {$period->code}");

        return ['closed' => true, 'message' => "Period {$period->label()} is closed."];
    }

    /** Reopen a closed period. A locked period cannot be reopened. */
    public function reopen(Period $period, string $reason): array
    {
        if ($period->status === PeriodStatus::Locked) {
            return ['reopened' => false, 'message' => "Period {$period->code} is locked and cannot be reopened."];
        }

        if ($period->isOpen()) {
            return ['reopened' => false, 'message' => "Period {$period->code} is already open."];
        }

        $period->update(['status' => PeriodStatus::Open, 'closed_by' => null, 'closed_at' => null]);

        $this->audit->record(
            'period_reopened',
            $period,
            description: "Reopened period {$period->code}",
            reason: $reason,
        );

        return ['reopened' => true, 'message' => "Period {$period->label()} is open again."];
    }

    public function lock(Period $period): void
    {
        $period->update([
            'status' => PeriodStatus::Locked,
            'closed_by' => Auth::id(),
            'closed_at' => $period->closed_at ?? now(),
        ]);

        $this->audit->record('period_locked', $period, description: "Locked period {$period->code}");
    }

    /** @return \Illuminate\Support\Collection<int,Period> */
    public function between(Carbon $from, Carbon $to)
    {
        return Period::whereDate('end_date', '>=', $from)
            ->whereDate('start_date', '<=', $to)
            ->orderBy('start_date')
            ->get();
    }
}
