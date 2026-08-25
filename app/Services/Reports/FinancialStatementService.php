<?php

namespace App\Services\Reports;

use App\Enums\AccountType;
use App\Models\Account;
use App\Services\Accounting\LedgerService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Scope of work §8.1 — the statutory reports: income statement, balance sheet,
 * cash flow statement, trial balance and general ledger detail.
 */
class FinancialStatementService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Income statement, grouped the way the chart of accounts is structured:
     * revenue (4000), direct costs (5000), then the four operating classes.
     */
    public function incomeStatement(ReportFilters $filters): array
    {
        $ledgerFilters = $filters->ledgerFilters();
        $from = $filters->from;
        $to = $filters->to;

        $revenue = $this->section([4000], $from, $to, $ledgerFilters);
        $directCosts = $this->section([5000], $from, $to, $ledgerFilters);
        $payroll = $this->section([6000], $from, $to, $ledgerFilters);
        $admin = $this->section([7000], $from, $to, $ledgerFilters);
        $vehicle = $this->section([8000], $from, $to, $ledgerFilters);
        $sales = $this->section([9000], $from, $to, $ledgerFilters);

        $totalRevenue = $revenue['total'];
        $totalDirect = $directCosts['total'];
        $grossProfit = Money::round($totalRevenue - $totalDirect);
        $totalOperating = Money::round($payroll['total'] + $admin['total'] + $vehicle['total'] + $sales['total']);
        $netProfit = Money::round($grossProfit - $totalOperating);

        return [
            'filters' => $filters,
            'revenue' => $revenue,
            'direct_costs' => $directCosts,
            'operating' => [
                'payroll' => $payroll,
                'administrative' => $admin,
                'vehicle' => $vehicle,
                'sales_other' => $sales,
                'total' => $totalOperating,
            ],
            'totals' => [
                'revenue' => $totalRevenue,
                'direct_costs' => $totalDirect,
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? round($grossProfit / $totalRevenue * 100, 1) : 0.0,
                'operating_expenses' => $totalOperating,
                'net_profit' => $netProfit,
                'net_margin' => $totalRevenue > 0 ? round($netProfit / $totalRevenue * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * Income statement broken into columns — by month, quarter or year, as
     * §8.1 requires.
     *
     * @return array{periods: array<int,array{label:string, from:Carbon, to:Carbon}>, rows: array, totals: array}
     */
    public function incomeStatementByPeriod(ReportFilters $filters, string $granularity = 'month'): array
    {
        $columns = $this->buildColumns($filters, $granularity);
        $statements = [];

        foreach ($columns as $column) {
            $statements[$column['label']] = $this->incomeStatement(
                $filters->withRange($column['from'], $column['to'])
            );
        }

        $rows = [
            ['key' => 'revenue', 'label' => 'Revenue', 'emphasis' => false],
            ['key' => 'direct_costs', 'label' => 'Direct costs', 'emphasis' => false],
            ['key' => 'gross_profit', 'label' => 'Gross profit', 'emphasis' => true],
            ['key' => 'operating_expenses', 'label' => 'Operating expenses', 'emphasis' => false],
            ['key' => 'net_profit', 'label' => 'Net profit', 'emphasis' => true],
        ];

        foreach ($rows as &$row) {
            $row['values'] = [];

            foreach ($columns as $column) {
                $row['values'][$column['label']] = $statements[$column['label']]['totals'][$row['key']];
            }

            $row['total'] = Money::round(array_sum($row['values']));
        }

        return ['columns' => $columns, 'rows' => $rows, 'statements' => $statements];
    }

    /**
     * Balance sheet as at a date. Current year profit is computed from the
     * ledger rather than read from 3080, so the statement balances even before
     * the year-end closing entry has been made.
     */
    public function balanceSheet(ReportFilters $filters): array
    {
        $asAt = $filters->to;
        $ledgerFilters = $filters->ledgerFilters();

        $assets = $this->balanceSection([1000], $asAt, $ledgerFilters);
        $liabilities = $this->balanceSection([2000], $asAt, $ledgerFilters);
        $equityAccounts = $this->balanceSection([3000], $asAt, $ledgerFilters);

        $yearStart = $this->ledger->fiscalYearStart($asAt);
        $currentYearProfit = $this->ledger->profitSummary($yearStart, $asAt, $ledgerFilters)['net_profit'];
        $retainedBroughtForward = $this->ledger->retainedEarningsBroughtForward($asAt);

        // 3070 and 3080 already hold whatever closing entries have been posted;
        // the un-closed remainder is added here so the two sides agree.
        $postedRetained = $equityAccounts['total'];
        $totalEquity = Money::round($postedRetained + $retainedBroughtForward + $currentYearProfit);

        $totalAssets = $assets['total'];
        $totalLiabilities = $liabilities['total'];
        $difference = Money::round($totalAssets - $totalLiabilities - $totalEquity);

        return [
            'filters' => $filters,
            'as_at' => $asAt,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => [
                'accounts' => $equityAccounts['accounts'],
                'posted_total' => $postedRetained,
                'retained_brought_forward' => $retainedBroughtForward,
                'current_year_profit' => $currentYearProfit,
                'total' => $totalEquity,
            ],
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => Money::round($totalLiabilities + $totalEquity),
                'difference' => $difference,
                'balanced' => abs($difference) < 0.005,
            ],
        ];
    }

    /**
     * Cash flow statement, indirect method: net profit adjusted for non-cash
     * items and movements in working capital, then investing and financing.
     */
    public function cashFlow(ReportFilters $filters): array
    {
        $from = $filters->from;
        $to = $filters->to;
        $f = $filters->ledgerFilters();

        $profit = $this->ledger->profitSummary($from, $to, $f);

        // Depreciation and amortisation are charged to profit but move no cash.
        $depreciation = Money::round(
            $this->accountMovement('8050', $from, $to, $f)
            + $this->accountMovement('9060', $from, $to, $f)
            + $this->accountMovement('9070', $from, $to, $f)
        );

        // A rise in a receivable consumes cash; a rise in a payable releases it.
        $receivables = -$this->balanceChange('1030', $from, $to, $f);
        $inventory = -$this->balanceChange('1060', $from, $to, $f);
        $prepaid = -$this->balanceChange('1040', $from, $to, $f);
        $payables = $this->balanceChange('2010', $from, $to, $f);
        $accruedSalaries = $this->balanceChange('2020', $from, $to, $f);
        $payrollTaxes = $this->balanceChange('2030', $from, $to, $f);
        $revenueSharePayable = $this->balanceChange('2040', $from, $to, $f);
        $deferredRevenue = $this->balanceChange('2060', $from, $to, $f);
        $otherAccruals = $this->balanceChange('2080', $from, $to, $f);

        $operating = Money::round(
            $profit['net_profit'] + $depreciation + $receivables + $inventory + $prepaid
            + $payables + $accruedSalaries + $payrollTaxes + $revenueSharePayable
            + $deferredRevenue + $otherAccruals
        );

        // Buying a fixed asset is a debit to 1110-1140: cash out.
        $assetPurchases = Money::round(
            $this->balanceChange('1110', $from, $to, $f)
            + $this->balanceChange('1120', $from, $to, $f)
            + $this->balanceChange('1130', $from, $to, $f)
            + $this->balanceChange('1140', $from, $to, $f)
        );
        $investing = Money::round(-$assetPurchases);

        $loans = $this->balanceChange('2070', $from, $to, $f);
        $partnerCapital = Money::round(
            $this->balanceChange('3010', $from, $to, $f) + $this->balanceChange('3020', $from, $to, $f)
            + $this->balanceChange('3030', $from, $to, $f) + $this->balanceChange('3040', $from, $to, $f)
            + $this->balanceChange('3050', $from, $to, $f)
        );
        $drawings = $this->balanceChange('3060', $from, $to, $f);
        $distributionsPayable = $this->balanceChange('2050', $from, $to, $f);

        $financing = Money::round($loans + $partnerCapital + $drawings + $distributionsPayable);

        $opening = $this->ledger->cashPosition($from->copy()->subDay(), $f);
        $closing = $this->ledger->cashPosition($to, $f);

        return [
            'filters' => $filters,
            'operating' => [
                'net_profit' => $profit['net_profit'],
                'depreciation' => $depreciation,
                'receivables' => $receivables,
                'inventory' => $inventory,
                'prepaid' => $prepaid,
                'payables' => $payables,
                'accrued_salaries' => $accruedSalaries,
                'payroll_taxes' => $payrollTaxes,
                'revenue_share_payable' => $revenueSharePayable,
                'deferred_revenue' => $deferredRevenue,
                'other_accruals' => $otherAccruals,
                'total' => $operating,
            ],
            'investing' => ['asset_purchases' => Money::round(-$assetPurchases), 'total' => $investing],
            'financing' => [
                'loans' => $loans,
                'partner_capital' => $partnerCapital,
                'drawings' => $drawings,
                'distributions_payable' => $distributionsPayable,
                'total' => $financing,
            ],
            'summary' => [
                'net_movement' => Money::round($operating + $investing + $financing),
                'opening_cash' => $opening,
                'closing_cash' => $closing,
                // If these disagree the indirect build has missed an account —
                // shown rather than hidden, so it can be chased.
                'actual_movement' => Money::round($closing - $opening),
                'unexplained' => Money::round($closing - $opening - ($operating + $investing + $financing)),
            ],
        ];
    }

    public function trialBalance(ReportFilters $filters): array
    {
        return $this->ledger->trialBalance($filters->from, $filters->to, $filters->ledgerFilters()) + [
            'filters' => $filters,
        ];
    }

    public function generalLedger(Account $account, ReportFilters $filters): array
    {
        return $this->ledger->generalLedger($account, $filters->from, $filters->to, $filters->ledgerFilters()) + [
            'filters' => $filters,
            'account' => $account,
        ];
    }

    /**
     * Accounts and total for a set of classes over a window.
     *
     * The total sums `natural` — the section's own side — so that a contra
     * account is deducted rather than added. See LedgerService::balancesByClass.
     *
     * @return array{accounts: Collection, total: float}
     */
    private function section(array $classes, Carbon $from, Carbon $to, array $filters): array
    {
        $accounts = $this->ledger->balancesByClass($classes, $from, $to, $filters)
            ->filter(fn ($a) => ! Money::isZero($a->natural))
            ->values();

        return ['accounts' => $accounts, 'total' => Money::round($accounts->sum('natural'))];
    }

    /** The same, but cumulative from the beginning of time (balance sheet). */
    private function balanceSection(array $classes, Carbon $asAt, array $filters): array
    {
        $accounts = $this->ledger->balancesByClass($classes, null, $asAt, $filters)
            ->filter(fn ($a) => ! Money::isZero($a->natural))
            ->values();

        return ['accounts' => $accounts, 'total' => Money::round($accounts->sum('natural'))];
    }

    private function accountMovement(string $code, Carbon $from, Carbon $to, array $filters): float
    {
        $account = Account::where('code', $code)->first();

        return $account ? $this->ledger->accountMovement($account, $from, $to, $filters) : 0.0;
    }

    /** Change in a balance sheet account's balance across the window. */
    private function balanceChange(string $code, Carbon $from, Carbon $to, array $filters): float
    {
        $account = Account::where('code', $code)->first();

        if (! $account) {
            return 0.0;
        }

        return $this->ledger->accountMovement($account, $from, $to, $filters);
    }

    /** @return array<int,array{label:string, from:Carbon, to:Carbon}> */
    private function buildColumns(ReportFilters $filters, string $granularity): array
    {
        $columns = [];
        $cursor = match ($granularity) {
            'year' => $filters->from->copy()->startOfYear(),
            'quarter' => $filters->from->copy()->startOfQuarter(),
            default => $filters->from->copy()->startOfMonth(),
        };

        while ($cursor->lessThanOrEqualTo($filters->to)) {
            [$end, $label, $next] = match ($granularity) {
                'year' => [$cursor->copy()->endOfYear(), $cursor->format('Y'), $cursor->copy()->addYear()],
                'quarter' => [$cursor->copy()->endOfQuarter(), 'Q'.$cursor->quarter.' '.$cursor->format('Y'), $cursor->copy()->addQuarter()],
                default => [$cursor->copy()->endOfMonth(), $cursor->format('M Y'), $cursor->copy()->addMonth()],
            };

            $columns[] = [
                'label' => $label,
                'from' => $cursor->copy()->max($filters->from),
                'to' => $end->min($filters->to),
            ];

            $cursor = $next;
        }

        return $columns;
    }
}
