@extends('layouts.app')
@section('title', 'Project profitability')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Project profitability" :filters="$filters"/>

    <x-page-filters :action="route('reports.project-profitability')" :filters="$filters"
                    :projects="$projects" :departments="$departments"/>

    <div class="mt-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            Scope of work §7 — {{ \App\Support\Money::format($report['unallocated_overhead'], true) }} of overhead
            could not be attributed to a single project and has been spread
            {{ strtolower($report['allocation_method']->label()) }}.
            The ledger keeps that overhead where it was incurred; the allocation happens here, at reporting time.
        </div>
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project</th><th class="num">Revenue</th><th class="num">Direct costs</th>
                        <th class="num">Gross profit</th><th class="num">Direct overhead</th>
                        <th class="num">Allocated overhead</th><th class="num">Net profit</th><th class="num">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        @php
                            $margin = $row['revenue'] > 0 ? round($row['net_profit'] / $row['revenue'] * 100, 1) : null;
                        @endphp
                        <tr>
                            <td>
                                <span class="font-medium">{{ $row['project']->name }}</span>
                                <div class="eyebrow">{{ $row['project']->code }}</div>
                            </td>
                            <td class="num"><x-money :value="$row['revenue']"/></td>
                            <td class="num"><x-money :value="$row['direct_costs']" muted/></td>
                            <td class="num"><x-money :value="$row['gross_profit']" accounting/></td>
                            <td class="num"><x-money :value="$row['direct_overhead']" muted/></td>
                            <td class="num"><x-money :value="$row['allocated_overhead']" muted/></td>
                            <td class="num"><x-money :value="$row['net_profit']" accounting/></td>
                            <td class="num">{{ $margin === null ? '—' : $margin.'%' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No projects"/></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num"><x-money :value="$report['totals']['revenue']"/></td>
                        <td class="num"><x-money :value="$report['totals']['direct_costs']"/></td>
                        <td class="num"><x-money :value="$report['totals']['gross_profit']" accounting/></td>
                        <td class="num"><x-money :value="$report['totals']['direct_overhead']"/></td>
                        <td class="num"><x-money :value="$report['totals']['allocated_overhead']"/></td>
                        <td class="num"><x-money :value="$report['totals']['net_profit']" accounting/></td>
                        <td class="num">
                            {{ $report['totals']['revenue'] > 0
                                ? round($report['totals']['net_profit'] / $report['totals']['revenue'] * 100, 1).'%'
                                : '—' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
