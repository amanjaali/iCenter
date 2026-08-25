<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BusCompany;
use App\Models\Department;
use App\Models\Project;
use App\Services\Reports\FinancialStatementService;
use App\Services\Reports\OperationalReportService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\RevenueReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scope of work §8 — "Reporting is a core requirement of this system, not an
 * optional module."
 *
 * Every report shares one filter object and one export path, so a new report
 * inherits date range, project and department filtering and CSV export without
 * re-implementing them.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly RevenueReportService $revenue,
        private readonly OperationalReportService $operations,
    ) {}

    public function index()
    {
        $this->authorize('view-reports');

        return view('reports.index');
    }

    // -- §8.1 Statutory and core financial reports ---------------------------

    public function incomeStatement(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $granularity = $request->string('granularity')->toString() ?: 'month';
        $report = $this->statements->incomeStatement($filters);

        if ($request->input('export') === 'csv') {
            return $this->csv('income-statement', $filters, $this->incomeStatementRows($report));
        }

        return view('reports.income-statement', [
            'report' => $report,
            'byPeriod' => $this->statements->incomeStatementByPeriod($filters, $granularity),
            'granularity' => $granularity,
        ] + $this->filterOptions($filters));
    }

    public function balanceSheet(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->statements->balanceSheet($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Section', 'Code', 'Account', 'Amount']];

            foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities'] as $key => $label) {
                foreach ($report[$key]['accounts'] as $account) {
                    $rows[] = [$label, $account->code, $account->name, $account->natural];
                }
                $rows[] = ['', '', "Total {$label}", $report['totals'][$key]];
            }

            foreach ($report['equity']['accounts'] as $account) {
                $rows[] = ['Equity', $account->code, $account->name, $account->natural];
            }
            $rows[] = ['Equity', '', 'Retained earnings brought forward', $report['equity']['retained_brought_forward']];
            $rows[] = ['Equity', '', 'Current year profit', $report['equity']['current_year_profit']];
            $rows[] = ['', '', 'Total equity', $report['totals']['equity']];

            return $this->csv('balance-sheet', $filters, $rows);
        }

        return view('reports.balance-sheet', compact('report') + $this->filterOptions($filters));
    }

    public function cashFlow(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->statements->cashFlow($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Section', 'Item', 'Amount']];

            foreach (['operating', 'investing', 'financing'] as $section) {
                foreach ($report[$section] as $key => $value) {
                    $rows[] = [ucfirst($section), ucfirst(str_replace('_', ' ', $key)), $value];
                }
            }

            foreach ($report['summary'] as $key => $value) {
                $rows[] = ['Summary', ucfirst(str_replace('_', ' ', $key)), $value];
            }

            return $this->csv('cash-flow', $filters, $rows);
        }

        return view('reports.cash-flow', compact('report') + $this->filterOptions($filters));
    }

    public function trialBalance(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->statements->trialBalance($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Code', 'Account', 'Debit', 'Credit']];

            foreach ($report['rows'] as $row) {
                $rows[] = [$row->code, $row->name, $row->net_debit, $row->net_credit];
            }

            $rows[] = ['', 'Total', $report['total_debit'], $report['total_credit']];

            return $this->csv('trial-balance', $filters, $rows);
        }

        return view('reports.trial-balance', compact('report') + $this->filterOptions($filters));
    }

    public function generalLedger(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $account = $request->integer('account_id')
            ? Account::find($request->integer('account_id'))
            : Account::byCode(config('allva.accounts.bank'));

        $report = $this->statements->generalLedger($account, $filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Date', 'Reference', 'Description', 'Cost centre', 'Project', 'Debit', 'Credit', 'Balance']];
            $rows[] = ['', '', 'Opening balance', '', '', '', '', $report['opening']];

            foreach ($report['rows'] as $row) {
                $rows[] = [
                    $row->date->toDateString(), $row->reference, $row->description,
                    $row->department, $row->project, $row->debit, $row->credit, $row->balance,
                ];
            }

            $rows[] = ['', '', 'Closing balance', '', '', '', '', $report['closing']];

            return $this->csv('general-ledger-'.$account->code, $filters, $rows);
        }

        return view('reports.general-ledger', [
            'report' => $report,
            'account' => $account,
            'accounts' => Account::postable()->orderBy('code')->get(),
        ] + $this->filterOptions($filters));
    }

    // -- §8.2 Project and revenue reports ------------------------------------

    public function projectProfitability(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->revenue->projectProfitability($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Project', 'Revenue', 'Direct costs', 'Gross profit', 'Direct overhead', 'Allocated overhead', 'Net profit']];

            foreach ($report['rows'] as $row) {
                $rows[] = [
                    $row['project']->name, $row['revenue'], $row['direct_costs'], $row['gross_profit'],
                    $row['direct_overhead'], $row['allocated_overhead'], $row['net_profit'],
                ];
            }

            return $this->csv('project-profitability', $filters, $rows);
        }

        return view('reports.project-profitability', compact('report') + $this->filterOptions($filters));
    }

    public function revenueSplit(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->revenue->revenueSplit($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Month', 'Revenue', 'Operating expenses', 'Base', $report['partner_name'].' share', 'ALLVA share']];

            foreach ($report['rows'] as $row) {
                $rows[] = [$row['label'], $row['revenue'], $row['operating_expenses'], $row['base'], $row['partner_share'], $row['allva_share']];
            }

            $t = $report['totals'];
            $rows[] = ['Total', $t['revenue'], $t['operating_expenses'], $t['base'], $t['partner_share'], $t['allva_share']];

            return $this->csv('revenue-split', $filters, $rows);
        }

        return view('reports.revenue-split', compact('report') + $this->filterOptions($filters));
    }

    public function recurringRevenue(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->revenue->monthlyRecurringRevenue($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Month', 'Academic year', 'Billable month', 'Active students', 'Projected revenue', 'Invoiced', 'Variance']];

            foreach ($report['rows'] as $row) {
                $rows[] = [
                    $row['label'], $row['academic_year'], $row['is_billable_month'] ? 'Yes' : 'No',
                    $row['active_students'], $row['projected_revenue'], $row['invoiced'], $row['variance'],
                ];
            }

            return $this->csv('monthly-recurring-revenue', $filters, $rows);
        }

        return view('reports.recurring-revenue', compact('report') + $this->filterOptions($filters));
    }

    public function activeStudents(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->revenue->activeStudents($filters);

        if ($request->input('export') === 'csv') {
            $rows = [array_merge(['Bus company', 'Bus'], $report['months'])];

            foreach ($report['rows'] as $companyRow) {
                $rows[] = array_merge(
                    [$companyRow['company']->name, 'All buses'],
                    array_map(fn ($c) => $c['active'], $companyRow['cells']),
                );

                foreach ($companyRow['buses'] as $busRow) {
                    $rows[] = array_merge(
                        ['', $busRow['bus']->code],
                        array_map(fn ($c) => $c['active'], $busRow['cells']),
                    );
                }
            }

            return $this->csv('active-students', $filters, $rows);
        }

        return view('reports.active-students', compact('report') + $this->filterOptions($filters));
    }

    public function rateHistory(Request $request)
    {
        $this->authorize('view-reports');

        $company = $request->integer('bus_company_id') ? BusCompany::find($request->integer('bus_company_id')) : null;
        $report = $this->revenue->rateHistory($company);

        if ($request->input('export') === 'csv') {
            $rows = [['Academic year', 'Applies to', 'Amount', 'Effective from', 'Effective to', 'Active', 'Set by']];

            foreach ($report['rows'] as $rate) {
                $rows[] = [
                    $rate->academicYear?->name,
                    $rate->busCompany?->name ?? 'All bus companies',
                    $rate->amount,
                    $rate->effective_from->toDateString(),
                    $rate->effective_to?->toDateString() ?? 'Open ended',
                    $rate->is_active ? 'Yes' : 'No',
                    $rate->createdBy?->name ?? 'System',
                ];
            }

            return $this->csvRaw('rate-history', $rows);
        }

        return view('reports.rate-history', [
            'report' => $report,
            'companies' => BusCompany::orderBy('name')->get(),
        ]);
    }

    public function billingCollection(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->revenue->billingVersusCollection($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Bus company', 'Month', 'Students', 'Invoiced', 'Collected', 'Outstanding']];

            foreach ($report['rows'] as $row) {
                $rows[] = [
                    $row['company']->name, $row['month_label'], $row['students'],
                    $row['invoiced'], $row['collected'], $row['outstanding'],
                ];
            }

            $t = $report['totals'];
            $rows[] = ['Total', '', '', $t['invoiced'], $t['collected'], $t['outstanding']];

            return $this->csv('billing-against-collection', $filters, $rows);
        }

        return view('reports.billing-collection', compact('report') + $this->filterOptions($filters));
    }

    public function busCompanyStatement(Request $request, BusCompany $busCompany)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->revenue->busCompanyStatement($busCompany, $filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Date', 'Type', 'Reference', 'Description', 'Charged', 'Paid', 'Balance']];
            $rows[] = ['', '', '', 'Opening balance', '', '', $report['opening_balance']];

            foreach ($report['entries'] as $entry) {
                $rows[] = [
                    $entry->date->toDateString(), $entry->type, $entry->reference,
                    $entry->description, $entry->debit, $entry->credit, $entry->balance,
                ];
            }

            $rows[] = ['', '', '', 'Closing balance', '', '', $report['closing_balance']];

            return $this->csv('statement-'.$busCompany->code, $filters, $rows);
        }

        return view('reports.bus-company-statement', compact('report') + $this->filterOptions($filters));
    }

    public function academicYearComparison(Request $request)
    {
        $this->authorize('view-reports');

        $report = $this->revenue->academicYearComparison();

        if ($request->input('export') === 'csv') {
            $rows = [['Academic year', 'Standard rate', 'Months billed', 'Average students', 'Peak students', 'Invoiced', 'Collected', 'Outstanding', 'Revenue growth %']];

            foreach ($report['rows'] as $row) {
                $rows[] = [
                    $row['year']->name, $row['standard_rate'], $row['months_billed'],
                    $row['average_students'], $row['peak_students'],
                    $row['invoiced'], $row['collected'], $row['outstanding'],
                    $row['revenue_growth'] ?? '',
                ];
            }

            return $this->csvRaw('academic-year-comparison', $rows);
        }

        return view('reports.academic-year-comparison', compact('report'));
    }

    // -- §8.3 Partner and expense reports ------------------------------------

    public function partnerDistribution(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->operations->partnerDistribution($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Partner', 'Ownership %', 'Declared', 'Paid', 'Outstanding']];

            foreach ($report['rows'] as $row) {
                $rows[] = [
                    $row['partner']->name, $row['ownership_percent'],
                    $row['declared'], $row['paid'], $row['outstanding'],
                ];
            }

            $t = $report['totals'];
            $rows[] = ['Total', $t['ownership_percent'], $t['declared'], $t['paid'], $t['outstanding']];

            return $this->csv('partner-distribution', $filters, $rows);
        }

        return view('reports.partner-distribution', compact('report') + $this->filterOptions($filters));
    }

    public function expenses(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->operations->expenses($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Code', 'Account', 'Class', 'Amount']];

            foreach ($report['by_account'] as $account) {
                $rows[] = [$account->code, $account->name, $account->class, $account->natural];
            }

            $rows[] = ['', 'Total', '', $report['totals']['total']];

            return $this->csv('expense-report', $filters, $rows);
        }

        return view('reports.expenses', compact('report') + $this->filterOptions($filters));
    }

    public function payroll(Request $request)
    {
        $this->authorize('view-reports');

        $filters = $this->filters($request);
        $report = $this->operations->payroll($filters);

        if ($request->input('export') === 'csv') {
            $rows = [['Employee', 'Cost centre', 'Months', 'Base', 'Overtime', 'Bonus', 'Allowances', 'Gross', 'Deductions', 'Net', 'Employer SS', 'Total cost']];

            foreach ($report['by_employee'] as $row) {
                $rows[] = [
                    $row['employee']?->name, $row['department']?->name, $row['months'],
                    $row['base_salary'], $row['overtime'], $row['bonus'], $row['allowances'],
                    $row['gross'], $row['deductions'], $row['net'], $row['employer_ss'], $row['total_cost'],
                ];
            }

            return $this->csv('payroll-report', $filters, $rows);
        }

        return view('reports.payroll', compact('report') + $this->filterOptions($filters));
    }

    public function receivablesAgeing(Request $request)
    {
        $this->authorize('view-reports');

        $asAt = $request->filled('as_at') ? Carbon::parse($request->string('as_at')->toString()) : now();
        $report = $this->operations->receivablesAgeing($asAt, $request->integer('bus_company_id') ?: null);

        if ($request->input('export') === 'csv') {
            $rows = [['Bus company', 'Not yet due', '1-30 days', '31-60 days', '61-90 days', 'Over 90 days', 'Total']];

            foreach ($report['by_company'] as $row) {
                $rows[] = [
                    $row['company']?->name, $row['current'], $row['1_30'],
                    $row['31_60'], $row['61_90'], $row['over_90'], $row['total'],
                ];
            }

            return $this->csvRaw('receivables-ageing', $rows);
        }

        return view('reports.receivables-ageing', [
            'report' => $report,
            'asAt' => $asAt,
            'companies' => BusCompany::orderBy('name')->get(),
        ]);
    }

    // -- helpers -------------------------------------------------------------

    private function filters(Request $request): ReportFilters
    {
        return ReportFilters::fromRequest($request->all());
    }

    private function filterOptions(ReportFilters $filters): array
    {
        return [
            'filters' => $filters,
            'projects' => Project::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'companies' => BusCompany::orderBy('name')->get(),
        ];
    }

    /** @return array<int,array<int,mixed>> */
    private function incomeStatementRows(array $report): array
    {
        $rows = [['Section', 'Code', 'Account', 'Amount']];

        foreach ($report['revenue']['accounts'] as $account) {
            $rows[] = ['Revenue', $account->code, $account->name, $account->natural];
        }
        $rows[] = ['', '', 'Total revenue', $report['totals']['revenue']];

        foreach ($report['direct_costs']['accounts'] as $account) {
            $rows[] = ['Direct costs', $account->code, $account->name, $account->natural];
        }
        $rows[] = ['', '', 'Gross profit', $report['totals']['gross_profit']];

        foreach ($report['operating'] as $key => $section) {
            if ($key === 'total') {
                continue;
            }

            foreach ($section['accounts'] as $account) {
                $rows[] = [ucfirst(str_replace('_', ' ', $key)), $account->code, $account->name, $account->natural];
            }
        }

        $rows[] = ['', '', 'Total operating expenses', $report['totals']['operating_expenses']];
        $rows[] = ['', '', 'Net profit', $report['totals']['net_profit']];

        return $rows;
    }

    /**
     * §8 — "must be exportable". Streamed so a large general ledger does not
     * have to be held in memory, with a BOM so Excel opens the Arabic account
     * names correctly.
     */
    private function csv(string $name, ReportFilters $filters, array $rows): StreamedResponse
    {
        array_unshift(
            $rows,
            ['ALLVA Accounting — '.str_replace('-', ' ', $name)],
            ['Period', $filters->label()],
            ['Generated', now()->format('j M Y H:i')],
            ['Currency', config('allva.currency.code')],
            [],
        );

        return $this->csvRaw($name, $rows);
    }

    private function csvRaw(string $name, array $rows): StreamedResponse
    {
        $filename = $name.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM: without it Excel mangles the Arabic account names.
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, escape: '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
