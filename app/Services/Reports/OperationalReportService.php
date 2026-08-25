<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Department;
use App\Models\DistributionLine;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Services\Accounting\LedgerService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Scope of work §8.3 — partner and expense reports.
 */
class OperationalReportService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Partner distribution report: net profit after expenses, each partner's
     * share, amounts paid and amounts outstanding.
     */
    public function partnerDistribution(ReportFilters $filters): array
    {
        $lines = DistributionLine::with(['partner', 'run', 'payouts'])
            ->whereHas('run', function ($q) use ($filters) {
                $q->where('status', '!=', 'draft')
                    ->whereDate('period_end', '>=', $filters->from->toDateString())
                    ->whereDate('period_start', '<=', $filters->to->toDateString());
            })
            ->when($filters->partnerId, fn ($q) => $q->where('partner_id', $filters->partnerId))
            ->get();

        $byPartner = Partner::active()
            ->when($filters->partnerId, fn ($q) => $q->where('id', $filters->partnerId))
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(function (Partner $partner) use ($lines) {
                $partnerLines = $lines->where('partner_id', $partner->id);

                return [
                    'partner' => $partner,
                    'ownership_percent' => (float) $partner->ownership_percent,
                    'declared' => Money::round($partnerLines->sum('share_amount')),
                    'paid' => Money::round($partnerLines->sum('paid_amount')),
                    'outstanding' => Money::round($partnerLines->sum('outstanding_amount')),
                    'lines' => $partnerLines->sortByDesc(fn (DistributionLine $l) => $l->run->period_end)->values(),
                ];
            });

        $profit = $this->ledger->profitSummary($filters->from, $filters->to, $filters->ledgerFilters());

        return [
            'filters' => $filters,
            'profit' => $profit,
            'rows' => $byPartner,
            'totals' => [
                'declared' => Money::round($byPartner->sum('declared')),
                'paid' => Money::round($byPartner->sum('paid')),
                'outstanding' => Money::round($byPartner->sum('outstanding')),
                'ownership_percent' => round($byPartner->sum('ownership_percent'), 4),
            ],
        ];
    }

    /**
     * Expense report by account, by department and by period, with the §7
     * classifications broken out.
     */
    public function expenses(ReportFilters $filters): array
    {
        $f = $filters->ledgerFilters();
        $from = $filters->from;
        $to = $filters->to;

        $byAccount = $this->ledger->balancesByClass([5000, 6000, 7000, 8000, 9000], $from, $to, $f)
            ->filter(fn ($a) => ! Money::isZero($a->natural))
            ->values();

        $byDepartment = Department::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(function (Department $department) use ($from, $to, $f) {
                $scoped = $f;
                $scoped['department_id'] = $department->id;

                return [
                    'department' => $department,
                    'direct_costs' => $this->ledger->classTotal([5000], $from, $to, $scoped),
                    'payroll' => $this->ledger->classTotal([6000], $from, $to, $scoped),
                    'administrative' => $this->ledger->classTotal([7000], $from, $to, $scoped),
                    'vehicle' => $this->ledger->classTotal([8000], $from, $to, $scoped),
                    'sales_other' => $this->ledger->classTotal([9000], $from, $to, $scoped),
                    'total' => $this->ledger->classTotal([5000, 6000, 7000, 8000, 9000], $from, $to, $scoped),
                ];
            })
            ->filter(fn (array $r) => ! Money::isZero($r['total']))
            ->values();

        $byMonth = collect($filters->months())->map(function (Carbon $month) use ($filters, $f) {
            $start = $month->copy()->startOfMonth()->max($filters->from);
            $end = $month->copy()->endOfMonth()->min($filters->to);

            return [
                'label' => $month->format('M Y'),
                'direct_costs' => $this->ledger->classTotal([5000], $start, $end, $f),
                'operating' => $this->ledger->classTotal([6000, 7000, 8000, 9000], $start, $end, $f),
                'total' => $this->ledger->classTotal([5000, 6000, 7000, 8000, 9000], $start, $end, $f),
            ];
        });

        // §7 — fixed against variable, and capex against opex.
        $fixed = $this->ledger->classTotal([5000, 6000, 7000, 8000, 9000], $from, $to, $f + ['nature' => 'fixed']);
        $variable = $this->ledger->classTotal([5000, 6000, 7000, 8000, 9000], $from, $to, $f + ['nature' => 'variable']);
        $capex = $this->capitalSpend($from, $to, $f);

        $total = Money::round($byAccount->sum('natural'));

        return [
            'filters' => $filters,
            'by_account' => $byAccount,
            'by_department' => $byDepartment,
            'by_month' => $byMonth,
            'classification' => [
                'fixed' => $fixed,
                'variable' => $variable,
                'unclassified' => Money::round($total - $fixed - $variable),
                'opex' => $total,
                'capex' => $capex,
            ],
            'totals' => [
                'direct_costs' => $this->ledger->classTotal([5000], $from, $to, $f),
                'operating' => $this->ledger->classTotal([6000, 7000, 8000, 9000], $from, $to, $f),
                'total' => $total,
            ],
        ];
    }

    /**
     * Payroll report per employee and per department, over whatever payroll
     * runs fall in the window.
     */
    public function payroll(ReportFilters $filters): array
    {
        $runs = PayrollRun::with('period')
            ->where('status', '!=', 'draft')
            ->whereBetween('payroll_month', [
                $filters->from->copy()->startOfMonth()->toDateString(),
                $filters->to->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('payroll_month')
            ->get();

        $lines = PayrollLine::with(['employee', 'department'])
            ->whereIn('payroll_run_id', $runs->pluck('id'))
            ->when($filters->departmentId, fn ($q) => $q->where('department_id', $filters->departmentId))
            ->get();

        $byEmployee = $lines->groupBy('employee_id')->map(function (Collection $group) {
            $first = $group->first();

            return [
                'employee' => $first->employee,
                'department' => $first->department,
                'months' => $group->count(),
                'base_salary' => Money::round($group->sum('base_salary')),
                'overtime' => Money::round($group->sum('overtime')),
                'bonus' => Money::round($group->sum('bonus')),
                'allowances' => Money::round($group->sum('allowances')),
                'gross' => Money::round($group->sum('gross')),
                'deductions' => Money::round(
                    $group->sum('employee_ss') + $group->sum('income_tax')
                    + $group->sum('advances_deducted') + $group->sum('other_deductions')
                ),
                'net' => Money::round($group->sum('net')),
                'employer_ss' => Money::round($group->sum('employer_ss')),
                'total_cost' => Money::round($group->sum('gross') + $group->sum('employer_ss')),
            ];
        })->sortBy(fn (array $r) => $r['employee']?->name ?? '')->values();

        $byDepartment = $lines->groupBy('department_id')->map(function (Collection $group) {
            return [
                'department' => $group->first()->department,
                'employees' => $group->pluck('employee_id')->unique()->count(),
                'gross' => Money::round($group->sum('gross')),
                'deductions' => Money::round(
                    $group->sum('employee_ss') + $group->sum('income_tax')
                    + $group->sum('advances_deducted') + $group->sum('other_deductions')
                ),
                'net' => Money::round($group->sum('net')),
                'employer_ss' => Money::round($group->sum('employer_ss')),
                'total_cost' => Money::round($group->sum('gross') + $group->sum('employer_ss')),
            ];
        })->sortBy(fn (array $r) => $r['department']?->name ?? '')->values();

        return [
            'filters' => $filters,
            'runs' => $runs,
            'by_employee' => $byEmployee,
            'by_department' => $byDepartment,
            'totals' => [
                'employees' => $byEmployee->count(),
                'gross' => Money::round($byEmployee->sum('gross')),
                'deductions' => Money::round($byEmployee->sum('deductions')),
                'net' => Money::round($byEmployee->sum('net')),
                'employer_ss' => Money::round($byEmployee->sum('employer_ss')),
                'total_cost' => Money::round($byEmployee->sum('total_cost')),
            ],
            'headcount' => Employee::active()->count(),
        ];
    }

    /**
     * Receivables ageing: outstanding invoices bucketed by how long they have
     * been overdue.
     */
    public function receivablesAgeing(?Carbon $asAt = null, ?int $busCompanyId = null): array
    {
        $asAt = ($asAt ?? Carbon::today())->copy()->endOfDay();

        $invoices = Invoice::with('busCompany')
            ->outstanding()
            ->when($busCompanyId, fn ($q) => $q->where('bus_company_id', $busCompanyId))
            ->whereDate('issue_date', '<=', $asAt->toDateString())
            ->orderBy('due_date')
            ->get();

        $bands = [
            'current' => ['label' => 'Not yet due', 'min' => PHP_INT_MIN, 'max' => 0, 'amount' => 0.0, 'invoices' => collect()],
            '1_30' => ['label' => '1 – 30 days', 'min' => 1, 'max' => 30, 'amount' => 0.0, 'invoices' => collect()],
            '31_60' => ['label' => '31 – 60 days', 'min' => 31, 'max' => 60, 'amount' => 0.0, 'invoices' => collect()],
            '61_90' => ['label' => '61 – 90 days', 'min' => 61, 'max' => 90, 'amount' => 0.0, 'invoices' => collect()],
            'over_90' => ['label' => 'Over 90 days', 'min' => 91, 'max' => PHP_INT_MAX, 'amount' => 0.0, 'invoices' => collect()],
        ];

        $byCompany = [];

        foreach ($invoices as $invoice) {
            $daysOverdue = $invoice->due_date->lt($asAt)
                ? (int) $invoice->due_date->diffInDays($asAt)
                : 0;

            $key = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => 'over_90',
            };

            $balance = (float) $invoice->balance_due;
            $bands[$key]['amount'] = Money::round($bands[$key]['amount'] + $balance);
            $bands[$key]['invoices']->push($invoice);

            $companyId = $invoice->bus_company_id;
            $byCompany[$companyId] ??= [
                'company' => $invoice->busCompany,
                'current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0, 'total' => 0.0,
            ];
            $byCompany[$companyId][$key] = Money::round($byCompany[$companyId][$key] + $balance);
            $byCompany[$companyId]['total'] = Money::round($byCompany[$companyId]['total'] + $balance);
        }

        $total = Money::round(array_sum(array_column($bands, 'amount')));

        // Share of the total in each band, for the dashboard bars.
        foreach ($bands as $key => $band) {
            $bands[$key]['percent'] = $total > 0 ? round($band['amount'] / $total * 100, 1) : 0.0;
        }

        return [
            'as_at' => $asAt,
            'bands' => $bands,
            'by_company' => collect($byCompany)->sortByDesc('total')->values(),
            'invoices' => $invoices,
            'total' => $total,
            'ledger_receivable' => $this->ledger->balanceAsAt(Account::system('receivable'), $asAt),
        ];
    }

    /** Capital spend in the window — a debit to any fixed or intangible asset. */
    private function capitalSpend(Carbon $from, Carbon $to, array $filters): float
    {
        $row = $this->ledger->lines($from, $to, $filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.code', ['1110', '1120', '1130', '1140'])
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        return Money::round((float) ($row->debit ?? 0) - (float) ($row->credit ?? 0));
    }
}
