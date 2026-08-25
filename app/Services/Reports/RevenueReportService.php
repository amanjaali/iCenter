<?php

namespace App\Services\Reports;

use App\Enums\OverheadAllocationMethod;
use App\Enums\RevenueShareBasis;
use App\Models\AcademicYear;
use App\Models\BusCompany;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\StudentEnrollment;
use App\Services\Accounting\LedgerService;
use App\Services\Billing\RateResolver;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §8.2 — project and revenue reports.
 */
class RevenueReportService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SettingsService $settings,
        private readonly RateResolver $rates,
    ) {}

    /**
     * Project profitability: revenue, direct costs, allocated overheads and net
     * profit for one project.
     *
     * §7 requires overhead that cannot be attributed to a single project to be
     * spread by a defined method. That happens here, at reporting time — the
     * ledger keeps overhead where it was incurred.
     */
    public function projectProfitability(ReportFilters $filters): array
    {
        $from = $filters->from;
        $to = $filters->to;
        $method = $this->settings->overheadAllocationMethod();

        $projects = Project::where('is_active', true)
            ->when($filters->projectId, fn ($q) => $q->where('id', $filters->projectId))
            ->orderBy('name')
            ->get();

        // Costs booked with no project at all are the unattributed overhead
        // pool that has to be spread.
        $unallocatedOverhead = $this->unattributedOverhead($from, $to, $filters);

        $rows = $projects->map(function (Project $project) use ($from, $to, $filters) {
            $f = $filters->ledgerFilters();
            $f['project_id'] = $project->id;

            $revenue = $this->ledger->classTotal([4000], $from, $to, $f);
            $directCosts = $this->ledger->classTotal([5000], $from, $to, $f);
            $directOverhead = $this->ledger->classTotal([6000, 7000, 8000, 9000], $from, $to, $f);

            return [
                'project' => $project,
                'revenue' => $revenue,
                'direct_costs' => $directCosts,
                'gross_profit' => Money::round($revenue - $directCosts),
                'direct_overhead' => $directOverhead,
                'allocated_overhead' => 0.0,
                'net_profit' => Money::round($revenue - $directCosts - $directOverhead),
            ];
        })->values();

        $rows = $this->allocateOverhead($rows, $unallocatedOverhead, $method);

        $totals = [
            'revenue' => Money::round($rows->sum('revenue')),
            'direct_costs' => Money::round($rows->sum('direct_costs')),
            'gross_profit' => Money::round($rows->sum('gross_profit')),
            'direct_overhead' => Money::round($rows->sum('direct_overhead')),
            'allocated_overhead' => Money::round($rows->sum('allocated_overhead')),
            'net_profit' => Money::round($rows->sum('net_profit')),
        ];

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => $totals,
            'unallocated_overhead' => $unallocatedOverhead,
            'allocation_method' => $method,
        ];
    }

    /**
     * Scope of work §8.2 — revenue split report: total revenue, Cyber Gate 50%,
     * ALLVA 50%.
     */
    public function revenueSplit(ReportFilters $filters): array
    {
        $basis = $this->settings->revenueShareBasis();
        $percent = $this->settings->revenueSharePercent();
        $partnerName = $this->settings->revenueSharePartnerName();
        $f = $filters->ledgerFilters();

        $rows = [];

        foreach ($filters->months() as $month) {
            $monthStart = $month->copy()->startOfMonth()->max($filters->from);
            $monthEnd = $month->copy()->endOfMonth()->min($filters->to);

            $revenue = $this->ledger->classTotal([4000], $monthStart, $monthEnd, $f);
            $operating = $this->ledger->classTotal([6000, 7000, 8000, 9000], $monthStart, $monthEnd, $f);

            // The base the percentage applies to depends on the §10.1 decision.
            $base = $basis === RevenueShareBasis::Gross ? $revenue : max(0, Money::round($revenue - $operating));
            $share = Money::round($base * $percent / 100);

            $rows[] = [
                'month' => $month->copy(),
                'label' => $month->format('M Y'),
                'revenue' => $revenue,
                'operating_expenses' => $operating,
                'base' => $base,
                'partner_share' => $share,
                'allva_share' => Money::round($base - $share),
            ];
        }

        $rows = collect($rows);

        return [
            'filters' => $filters,
            'basis' => $basis,
            'percent' => $percent,
            'partner_name' => $partnerName,
            'rows' => $rows,
            'totals' => [
                'revenue' => Money::round($rows->sum('revenue')),
                'operating_expenses' => Money::round($rows->sum('operating_expenses')),
                'base' => Money::round($rows->sum('base')),
                'partner_share' => Money::round($rows->sum('partner_share')),
                'allva_share' => Money::round($rows->sum('allva_share')),
            ],
        ];
    }

    /**
     * Scope of work §8.2 — monthly recurring revenue: active students × the
     * applicable rate, by month. Taken from the registry, so it is a forward
     * looking figure independent of whether invoices have been raised.
     */
    public function monthlyRecurringRevenue(ReportFilters $filters): array
    {
        $rows = [];

        foreach ($filters->months() as $month) {
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            $academicYear = AcademicYear::forDate($monthStart);
            $billable = $academicYear?->isBillableMonth($monthStart) ?? false;

            $companies = BusCompany::active()
                ->when($filters->busCompanyId, fn ($q) => $q->where('id', $filters->busCompanyId))
                ->get();

            $students = 0;
            $mrr = 0.0;

            foreach ($companies as $company) {
                $count = $this->activeStudentCount($company->id, $monthStart, $monthEnd);

                if ($count === 0) {
                    continue;
                }

                $rate = $this->rates->resolve($company, $monthEnd);
                $students += $count;
                $mrr += $count * (float) ($rate->amount ?? 0);
            }

            $invoiced = Money::round(
                Invoice::issued()
                    ->when($filters->busCompanyId, fn ($q) => $q->where('bus_company_id', $filters->busCompanyId))
                    ->forMonth($monthStart)
                    ->sum('total')
            );

            $rows[] = [
                'month' => $monthStart,
                'label' => $monthStart->format('M Y'),
                'academic_year' => $academicYear?->name,
                'is_billable_month' => $billable,
                'active_students' => $students,
                'projected_revenue' => Money::round($billable ? $mrr : 0),
                'invoiced' => $invoiced,
                'variance' => Money::round($invoiced - ($billable ? $mrr : 0)),
            ];
        }

        $rows = collect($rows);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => [
                'projected_revenue' => Money::round($rows->sum('projected_revenue')),
                'invoiced' => Money::round($rows->sum('invoiced')),
                'variance' => Money::round($rows->sum('variance')),
                'peak_students' => (int) $rows->max('active_students'),
            ],
        ];
    }

    /**
     * Scope of work §8.2 — active student count per bus company, per bus, per
     * month, showing joins and exits.
     */
    public function activeStudents(ReportFilters $filters): array
    {
        $months = $filters->months();

        $companies = BusCompany::with(['buses' => fn ($q) => $q->orderBy('code')])
            ->when($filters->busCompanyId, fn ($q) => $q->where('id', $filters->busCompanyId))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($companies as $company) {
            $busRows = [];

            foreach ($company->buses as $bus) {
                $cells = [];

                foreach ($months as $month) {
                    $monthStart = $month->copy()->startOfMonth();
                    $monthEnd = $month->copy()->endOfMonth();

                    $cells[$month->format('M Y')] = [
                        'active' => $this->activeStudentCount($company->id, $monthStart, $monthEnd, $bus->id),
                        'joined' => $this->joinCount($company->id, $monthStart, $monthEnd, $bus->id),
                        'left' => $this->exitCount($company->id, $monthStart, $monthEnd, $bus->id),
                    ];
                }

                $busRows[] = ['bus' => $bus, 'cells' => $cells];
            }

            $companyCells = [];

            foreach ($months as $month) {
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();

                $companyCells[$month->format('M Y')] = [
                    'active' => $this->activeStudentCount($company->id, $monthStart, $monthEnd),
                    'joined' => $this->joinCount($company->id, $monthStart, $monthEnd),
                    'left' => $this->exitCount($company->id, $monthStart, $monthEnd),
                ];
            }

            $rows[] = ['company' => $company, 'cells' => $companyCells, 'buses' => $busRows];
        }

        return [
            'filters' => $filters,
            'months' => array_map(fn (Carbon $m) => $m->format('M Y'), $months),
            'rows' => collect($rows),
        ];
    }

    /**
     * Scope of work §8.2 — billing against collection: invoiced, collected and
     * outstanding, per bus company, per month.
     */
    public function billingVersusCollection(ReportFilters $filters): array
    {
        $invoices = Invoice::with('busCompany')
            ->issued()
            ->when($filters->busCompanyId, fn ($q) => $q->where('bus_company_id', $filters->busCompanyId))
            ->whereBetween('billing_month', [
                $filters->from->copy()->startOfMonth()->toDateString(),
                $filters->to->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('billing_month')
            ->get();

        $rows = $invoices
            ->groupBy(fn (Invoice $i) => $i->bus_company_id.'|'.$i->billing_month->format('Y-m'))
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'company' => $first->busCompany,
                    'month' => $first->billing_month->copy(),
                    'month_label' => $first->billing_month->format('M Y'),
                    'students' => (int) $group->sum('student_count'),
                    'invoiced' => Money::round($group->sum('total')),
                    'collected' => Money::round($group->sum('amount_paid')),
                    'outstanding' => Money::round($group->sum('balance_due')),
                    'invoices' => $group,
                ];
            })
            ->sortBy([['month_label', 'asc']])
            ->values();

        $totals = [
            'invoiced' => Money::round($rows->sum('invoiced')),
            'collected' => Money::round($rows->sum('collected')),
            'outstanding' => Money::round($rows->sum('outstanding')),
        ];

        $totals['collection_rate'] = $totals['invoiced'] > 0
            ? round($totals['collected'] / $totals['invoiced'] * 100, 1)
            : 0.0;

        return ['filters' => $filters, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Scope of work §8.2 — bus company statement: buses registered, students
     * per bus, amount billed, amount collected and outstanding balance.
     */
    public function busCompanyStatement(BusCompany $company, ReportFilters $filters): array
    {
        $invoices = $company->invoices()
            ->issued()
            ->whereBetween('billing_month', [
                $filters->from->copy()->startOfMonth()->toDateString(),
                $filters->to->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('billing_month')
            ->get();

        $payments = $company->payments()
            ->posted()
            ->whereBetween('payment_date', [$filters->from->toDateString(), $filters->to->toDateString()])
            ->orderBy('payment_date')
            ->get();

        // A running statement: invoices raised and receipts applied, in date
        // order, with the balance carried down.
        $entries = collect();

        foreach ($invoices as $invoice) {
            $entries->push((object) [
                'date' => $invoice->issue_date,
                'type' => 'invoice',
                'reference' => $invoice->number,
                'description' => 'Subscriptions — '.$invoice->billing_month->format('F Y')
                    .' ('.$invoice->student_count.' students)',
                'debit' => (float) $invoice->total,
                'credit' => 0.0,
                'model' => $invoice,
            ]);
        }

        foreach ($payments as $payment) {
            $entries->push((object) [
                'date' => $payment->payment_date,
                'type' => 'payment',
                'reference' => $payment->number,
                'description' => 'Payment received'.($payment->reference ? " — {$payment->reference}" : ''),
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
                'model' => $payment,
            ]);
        }

        $openingBalance = $this->openingBalanceFor($company, $filters->from);
        $running = $openingBalance;

        $entries = $entries->sortBy([['date', 'asc'], ['type', 'asc']])->values()
            ->map(function ($entry) use (&$running) {
                $running = Money::round($running + $entry->debit - $entry->credit);
                $entry->balance = $running;

                return $entry;
            });

        $buses = $company->buses()->orderBy('code')->get()->map(function ($bus) use ($filters) {
            return [
                'bus' => $bus,
                'students' => $this->activeStudentCount(
                    $bus->bus_company_id,
                    $filters->to->copy()->startOfMonth(),
                    $filters->to->copy()->endOfMonth(),
                    $bus->id
                ),
            ];
        });

        return [
            'filters' => $filters,
            'company' => $company,
            'buses' => $buses,
            'entries' => $entries,
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'totals' => [
                'invoiced' => Money::round($invoices->sum('total')),
                'collected' => Money::round($payments->sum('amount')),
                'outstanding' => $company->outstandingBalance(),
                'buses' => $buses->count(),
                'students' => (int) $buses->sum('students'),
            ],
        ];
    }

    /**
     * Scope of work §8.2 — academic year comparison, "once a second year
     * exists". Compares revenue, students and collection across years.
     */
    public function academicYearComparison(): array
    {
        $years = AcademicYear::orderBy('start_date')->get();

        $rows = $years->map(function (AcademicYear $year) {
            $invoices = Invoice::issued()
                ->where('academic_year_id', $year->id)
                ->get();

            $months = max(1, $invoices->pluck('billing_month')->map->format('Y-m')->unique()->count());

            $rate = $year->rateCards()->whereNull('bus_company_id')->orderBy('effective_from')->value('amount');

            return [
                'year' => $year,
                'standard_rate' => (float) ($rate ?? 0),
                'months_billed' => $months,
                'invoices' => $invoices->count(),
                'peak_students' => (int) ($invoices->max('student_count') ?? 0),
                'average_students' => (int) round($invoices->avg('student_count') ?? 0),
                'invoiced' => Money::round($invoices->sum('total')),
                'collected' => Money::round($invoices->sum('amount_paid')),
                'outstanding' => Money::round($invoices->sum('balance_due')),
                'average_monthly_revenue' => Money::round($invoices->sum('total') / $months),
            ];
        });

        // Growth against the immediately preceding year.
        $rows = $rows->map(function (array $row, int $index) use ($rows) {
            $previous = $index > 0 ? $rows[$index - 1] : null;

            $row['revenue_growth'] = $previous && $previous['invoiced'] > 0
                ? round(($row['invoiced'] - $previous['invoiced']) / $previous['invoiced'] * 100, 1)
                : null;

            $row['student_growth'] = $previous && $previous['average_students'] > 0
                ? round(($row['average_students'] - $previous['average_students']) / $previous['average_students'] * 100, 1)
                : null;

            return $row;
        });

        return [
            'rows' => $rows,
            'comparable' => $rows->count() > 1,
        ];
    }

    /** Scope of work §8.2 — rate history. */
    public function rateHistory(?BusCompany $company = null): array
    {
        return ['rows' => $this->rates->history($company), 'company' => $company];
    }

    // -- helpers -------------------------------------------------------------

    /** Distinct students enrolled at any point during a month. */
    private function activeStudentCount(int $companyId, Carbon $from, Carbon $to, ?int $busId = null): int
    {
        return (int) StudentEnrollment::query()
            ->where('bus_company_id', $companyId)
            ->when($busId, fn ($q) => $q->where('bus_id', $busId))
            ->billable()
            ->overlapping($from, $to)
            ->distinct()
            ->count('student_id');
    }

    private function joinCount(int $companyId, Carbon $from, Carbon $to, ?int $busId = null): int
    {
        return (int) StudentEnrollment::query()
            ->where('bus_company_id', $companyId)
            ->when($busId, fn ($q) => $q->where('bus_id', $busId))
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    private function exitCount(int $companyId, Carbon $from, Carbon $to, ?int $busId = null): int
    {
        return (int) StudentEnrollment::query()
            ->where('bus_company_id', $companyId)
            ->when($busId, fn ($q) => $q->where('bus_id', $busId))
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    /** Receivable balance for a company before the statement window opens. */
    private function openingBalanceFor(BusCompany $company, Carbon $from): float
    {
        $invoiced = (float) $company->invoices()->issued()
            ->whereDate('issue_date', '<', $from->toDateString())->sum('total');

        $paid = (float) $company->payments()->posted()
            ->whereDate('payment_date', '<', $from->toDateString())->sum('amount');

        return Money::round($invoiced - $paid);
    }

    /** Operating costs booked without a project — the pool to be spread. */
    private function unattributedOverhead(Carbon $from, Carbon $to, ReportFilters $filters): float
    {
        $row = $this->ledger->lines($from, $to, array_diff_key($filters->ledgerFilters(), ['project_id' => null]))
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.class', [6000, 7000, 8000, 9000])
            ->whereNull('jl.project_id')
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        return Money::round((float) ($row->debit ?? 0) - (float) ($row->credit ?? 0));
    }

    /** @param Collection<int,array> $rows */
    private function allocateOverhead(Collection $rows, float $pool, OverheadAllocationMethod $method): Collection
    {
        if ($method === OverheadAllocationMethod::None || Money::isZero($pool) || $rows->isEmpty()) {
            return $rows;
        }

        $receiving = $rows->filter(fn (array $r) => $r['project']->receives_overhead);

        if ($receiving->isEmpty()) {
            return $rows;
        }

        $weights = match ($method) {
            OverheadAllocationMethod::Revenue => $receiving->mapWithKeys(
                fn (array $r) => [$r['project']->id => max(0.0, (float) $r['revenue'])]
            )->all(),
            OverheadAllocationMethod::Headcount => $receiving->mapWithKeys(
                fn (array $r) => [$r['project']->id => (float) DB::table('employees')
                    ->where('project_id', $r['project']->id)->where('status', 'active')->count()]
            )->all(),
            default => $receiving->mapWithKeys(fn (array $r) => [$r['project']->id => 1.0])->all(),
        };

        // If the chosen driver is all zeros (no revenue yet, no headcount),
        // fall back to an equal split rather than dropping the overhead.
        if (array_sum($weights) <= 0) {
            $weights = array_map(fn () => 1.0, $weights);
        }

        $allocations = Money::allocate($pool, $weights);

        return $rows->map(function (array $row) use ($allocations) {
            $allocated = $allocations[$row['project']->id] ?? 0.0;
            $row['allocated_overhead'] = $allocated;
            $row['net_profit'] = Money::round($row['net_profit'] - $allocated);

            return $row;
        });
    }
}
