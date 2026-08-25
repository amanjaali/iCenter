@extends('layouts.app')
@section('title', 'Reports')
@section('subtitle', 'SCOPE OF WORK §8 · ALL FILTERABLE AND EXPORTABLE')

@section('content')
    @php
        $groups = [
            [
                'title' => 'Statutory and core financial',
                'reference' => '§8.1',
                'reports' => [
                    ['route' => 'reports.income-statement', 'name' => 'Income statement', 'icon' => 'chart',
                     'description' => 'Revenue, direct costs and operating expenses, by month, quarter or year.'],
                    ['route' => 'reports.balance-sheet', 'name' => 'Balance sheet', 'icon' => 'list',
                     'description' => 'Assets, liabilities and equity as at a date, with the two sides proved equal.'],
                    ['route' => 'reports.cash-flow', 'name' => 'Cash flow statement', 'icon' => 'wallet',
                     'description' => 'Operating, investing and financing, reconciled to the actual cash movement.'],
                    ['route' => 'reports.trial-balance', 'name' => 'Trial balance', 'icon' => 'book',
                     'description' => 'Every account with a movement, and the totals that must agree.'],
                    ['route' => 'reports.general-ledger', 'name' => 'General ledger detail', 'icon' => 'file',
                     'description' => 'Every posted entry against one account, with a running balance.'],
                ],
            ],
            [
                'title' => 'Project and revenue',
                'reference' => '§8.2',
                'reports' => [
                    ['route' => 'reports.project-profitability', 'name' => 'Project profitability', 'icon' => 'pie',
                     'description' => 'eTrackify revenue, direct costs, allocated overheads and net profit.'],
                    ['route' => 'reports.revenue-split', 'name' => 'Revenue split', 'icon' => 'split',
                     'description' => 'Total revenue, the revenue partner’s share, and ALLVA’s share.'],
                    ['route' => 'reports.recurring-revenue', 'name' => 'Monthly recurring revenue', 'icon' => 'refresh',
                     'description' => 'Active students multiplied by the applicable rate, month by month.'],
                    ['route' => 'reports.active-students', 'name' => 'Active student count', 'icon' => 'users',
                     'description' => 'Per bus company, per bus, per month, showing joins and exits.'],
                    ['route' => 'reports.rate-history', 'name' => 'Rate history', 'icon' => 'tag',
                     'description' => 'Which rate applied to which period and which company.'],
                    ['route' => 'reports.billing-collection', 'name' => 'Billing against collection', 'icon' => 'receipt',
                     'description' => 'Invoiced, collected and outstanding, per bus company, per month.'],
                    ['route' => 'reports.academic-year-comparison', 'name' => 'Academic year comparison', 'icon' => 'calendar',
                     'description' => 'One academic year against the next, once a second year exists.'],
                ],
            ],
            [
                'title' => 'Partner and expense',
                'reference' => '§8.3',
                'reports' => [
                    ['route' => 'reports.partner-distribution', 'name' => 'Partner distribution', 'icon' => 'handshake',
                     'description' => 'Net profit after expenses, each partner’s share, paid and outstanding.'],
                    ['route' => 'reports.expenses', 'name' => 'Expense report', 'icon' => 'receipt',
                     'description' => 'By account, by department and by period, with fixed/variable and capex/opex.'],
                    ['route' => 'reports.payroll', 'name' => 'Payroll report', 'icon' => 'badge',
                     'description' => 'Per employee and per department, including the employer’s own cost.'],
                    ['route' => 'reports.receivables-ageing', 'name' => 'Receivables ageing', 'icon' => 'alert',
                     'description' => 'Outstanding invoices bucketed by how long they have been overdue.'],
                ],
            ],
        ];
    @endphp

    <div class="mb-5 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            Every report reads only posted journals — drafts and reversed entries are invisible to reporting
            by construction. Each one filters by date range, project and department, and exports to CSV.
        </div>
    </div>

    @foreach ($groups as $group)
        <div class="mb-6">
            <div class="mb-3 flex items-baseline gap-2.5">
                <h2 class="font-display text-sm font-medium">{{ $group['title'] }}</h2>
                <span class="eyebrow">{{ $group['reference'] }}</span>
            </div>
            <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($group['reports'] as $report)
                    <a href="{{ route($report['route']) }}"
                       class="card flex items-start gap-3 p-4 transition-colors hover:border-line-strong">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-mist text-navy">
                            <x-icon :name="$report['icon']" class="size-4"/>
                        </span>
                        <span>
                            <span class="block text-xs font-medium">{{ $report['name'] }}</span>
                            <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ $report['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
