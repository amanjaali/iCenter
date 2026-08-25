@extends('layouts.app')
@section('title', 'Billing against collection')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Billing against collection" :filters="$filters"/>

    <x-page-filters :action="route('reports.billing-collection')" :filters="$filters" :companies="$companies"/>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-4">
        <x-kpi label="Invoiced" :value="\App\Support\Money::format($report['totals']['invoiced'])" accent="#0A1F3D"
               :delta="$filters->label()"/>
        <x-kpi label="Collected" :value="\App\Support\Money::format($report['totals']['collected'])" accent="#0E7C5A"
               :delta="$report['totals']['collection_rate'].'% collection rate'"/>
        <x-kpi label="Outstanding" :value="\App\Support\Money::format($report['totals']['outstanding'])"
               :accent="$report['totals']['outstanding'] > 0 ? '#B3261E' : '#98A2B3'" delta="Still to collect"/>
        <x-kpi label="Collection rate" :value="$report['totals']['collection_rate'].'%'" accent="#00C2E8"
               delta="Collected against invoiced"/>
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bus company</th><th>Month</th><th class="num">Students</th>
                        <th class="num">Invoiced</th><th class="num">Collected</th>
                        <th class="num">Outstanding</th><th class="num">Collected %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        @php $rate = $row['invoiced'] > 0 ? round($row['collected'] / $row['invoiced'] * 100, 1) : 0; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('reports.bus-company-statement', $row['company']) }}" class="hover:text-signal">
                                    {{ $row['company']->name }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap text-muted">{{ $row['month_label'] }}</td>
                            <td class="num">{{ number_format($row['students']) }}</td>
                            <td class="num"><x-money :value="$row['invoiced']"/></td>
                            <td class="num"><x-money :value="$row['collected']"/></td>
                            <td class="num"><x-money :value="$row['outstanding']" muted/></td>
                            <td class="num">
                                <span class="{{ $rate < 100 ? 'text-caution' : 'text-positive' }}">{{ $rate }}%</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No invoices in this period"/></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num"><x-money :value="$report['totals']['invoiced']"/></td>
                        <td class="num"><x-money :value="$report['totals']['collected']"/></td>
                        <td class="num"><x-money :value="$report['totals']['outstanding']"/></td>
                        <td class="num">{{ $report['totals']['collection_rate'] }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
