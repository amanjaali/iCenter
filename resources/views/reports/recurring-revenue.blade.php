@extends('layouts.app')
@section('title', 'Monthly recurring revenue')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Monthly recurring revenue" :filters="$filters"/>

    <x-page-filters :action="route('reports.recurring-revenue')" :filters="$filters"
                    :companies="$companies"/>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Projected revenue" :value="\App\Support\Money::format($report['totals']['projected_revenue'])"
               accent="#00C2E8" delta="Active students × the applicable rate"/>
        <x-kpi label="Actually invoiced" :value="\App\Support\Money::format($report['totals']['invoiced'])"
               accent="#0A1F3D" delta="From issued invoices"/>
        <x-kpi label="Variance" :value="\App\Support\Money::accounting($report['totals']['variance'])"
               :accent="abs($report['totals']['variance']) > 0.5 ? '#9A6206' : '#0E7C5A'"
               delta="Invoiced less projected"/>
    </div>

    @if (abs($report['totals']['variance']) > 0.5)
        <div class="mt-4 flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
            <x-icon name="info" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                Projected revenue counts every student enrolled at any point in the month at the full rate,
                while invoices prorate part months by the rule in force. A variance is therefore expected —
                a large one usually means a company has not been invoiced.
            </div>
        </div>
    @endif

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th><th>Academic year</th><th>Billable</th>
                        <th class="num">Active students</th><th class="num">Projected</th>
                        <th class="num">Invoiced</th><th class="num">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr class="{{ $row['is_billable_month'] ? '' : 'opacity-60' }}">
                            <td>{{ $row['label'] }}</td>
                            <td class="text-muted">{{ $row['academic_year'] ?? '—' }}</td>
                            <td>
                                <x-badge :tone="$row['is_billable_month'] ? 'success' : 'neutral'"
                                         :label="$row['is_billable_month'] ? 'Yes' : 'Paused'"/>
                            </td>
                            <td class="num">{{ number_format($row['active_students']) }}</td>
                            <td class="num"><x-money :value="$row['projected_revenue']"/></td>
                            <td class="num"><x-money :value="$row['invoiced']"/></td>
                            <td class="num"><x-money :value="$row['variance']" accounting/></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total · peak {{ number_format($report['totals']['peak_students']) }} students</td>
                        <td class="num"></td>
                        <td class="num"><x-money :value="$report['totals']['projected_revenue']"/></td>
                        <td class="num"><x-money :value="$report['totals']['invoiced']"/></td>
                        <td class="num"><x-money :value="$report['totals']['variance']" accounting/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
