@extends('layouts.app')
@section('title', 'Income statement')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Income statement" :filters="$filters"/>

    <x-page-filters :action="route('reports.income-statement')" :filters="$filters"
                    :projects="$projects" :departments="$departments">
        <div class="min-w-[130px]">
            <label class="label" for="granularity">Columns</label>
            <select id="granularity" name="granularity" class="select">
                @foreach (['month' => 'By month', 'quarter' => 'By quarter', 'year' => 'By year'] as $v => $l)
                    <option value="{{ $v }}" @selected($granularity === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </x-page-filters>

    <div class="mt-4 grid gap-4 xl:grid-cols-[420px_1fr]">
        <div class="card card-pad self-start">
            <div class="eyebrow mb-4">Summary · {{ $filters->label() }}</div>
            <x-stat-row label="Revenue" :value="$report['totals']['revenue']"/>
            <x-stat-row label="Direct costs" :value="-$report['totals']['direct_costs']" indent/>
            <x-stat-row label="Gross profit" :value="$report['totals']['gross_profit']" emphasis/>
            <x-stat-row label="Operating expenses" :value="-$report['totals']['operating_expenses']" indent/>
            <x-stat-row label="Net profit" :value="$report['totals']['net_profit']" emphasis/>

            <div class="mt-3 flex flex-col gap-1.5 border-t border-rule pt-3">
                <div class="flex justify-between text-xs">
                    <span class="text-muted">Gross margin</span>
                    <span class="money">{{ $report['totals']['gross_margin'] }}%</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-muted">Net margin</span>
                    <span class="money {{ $report['totals']['net_margin'] < 0 ? 'money-negative' : '' }}">
                        {{ $report['totals']['net_margin'] }}%
                    </span>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Detail by account</div>
                    <div class="card-sub">Grouped by the chart of accounts classes from §6.</div>
                </div>
            </div>
            <table class="table table-compact">
                <tbody>
                    @php
                        $sections = [
                            ['label' => 'Revenue', 'class' => '4000', 'data' => $report['revenue'], 'total' => $report['totals']['revenue']],
                            ['label' => 'Direct costs', 'class' => '5000', 'data' => $report['direct_costs'], 'total' => $report['totals']['direct_costs']],
                            ['label' => 'Payroll expenses', 'class' => '6000', 'data' => $report['operating']['payroll'], 'total' => $report['operating']['payroll']['total']],
                            ['label' => 'Office and administrative', 'class' => '7000', 'data' => $report['operating']['administrative'], 'total' => $report['operating']['administrative']['total']],
                            ['label' => 'Vehicle expenses', 'class' => '8000', 'data' => $report['operating']['vehicle'], 'total' => $report['operating']['vehicle']['total']],
                            ['label' => 'Sales, marketing and other', 'class' => '9000', 'data' => $report['operating']['sales_other'], 'total' => $report['operating']['sales_other']['total']],
                        ];
                    @endphp

                    @foreach ($sections as $section)
                        <tr class="bg-mist">
                            <td colspan="2" class="font-medium">
                                <span class="money me-2 text-faint">{{ $section['class'] }}</span>{{ $section['label'] }}
                            </td>
                            <td class="num font-medium"><x-money :value="$section['total']" accounting/></td>
                        </tr>
                        @forelse ($section['data']['accounts'] as $account)
                            <tr>
                                <td class="w-20 ps-6"><span class="money text-xs text-muted">{{ $account->code }}</span></td>
                                <td>
                                    <a href="{{ route('accounts.show', $account->id) }}" class="hover:text-signal">{{ $account->name }}</a>
                                    @if ($account->is_contra)
                                        <span class="badge badge-neutral ms-1.5">Contra</span>
                                    @endif
                                </td>
                                <td class="num"><x-money :value="$account->natural" accounting/></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="ps-6 text-faint">Nothing posted in this period</td></tr>
                        @endforelse

                        @if ($section['class'] === '5000')
                            <tr class="border-t border-line-strong">
                                <td colspan="2" class="font-medium">Gross profit</td>
                                <td class="num font-medium"><x-money :value="$report['totals']['gross_profit']" accounting/></td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Net profit</td>
                        <td class="num"><x-money :value="$report['totals']['net_profit']" accounting/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if (count($byPeriod['columns']) > 1)
        <div class="card mt-4 overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">By {{ $granularity }}</div>
                    <div class="card-sub">Scope of work §8.1 — the income statement by month, by quarter and by year.</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($byPeriod['columns'] as $column)
                                <th class="num">{{ $column['label'] }}</th>
                            @endforeach
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byPeriod['rows'] as $row)
                            <tr class="{{ $row['emphasis'] ? 'border-t border-line-strong font-medium' : '' }}">
                                <td>{{ $row['label'] }}</td>
                                @foreach ($byPeriod['columns'] as $column)
                                    <td class="num"><x-money :value="$row['values'][$column['label']]" accounting/></td>
                                @endforeach
                                <td class="num"><x-money :value="$row['total']" accounting/></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
