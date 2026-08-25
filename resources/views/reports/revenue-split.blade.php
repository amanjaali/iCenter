@extends('layouts.app')
@section('title', 'Revenue split')
@section('subtitle', strtoupper($report['partner_name'].' '.rtrim(rtrim(number_format($report['percent'], 2), '0'), '.').'% · '.$report['basis']->label()))

@section('content')
    <x-report-header title="Revenue split" :filters="$filters"/>

    <x-page-filters :action="route('reports.revenue-split')" :filters="$filters"
                    :projects="$projects" :departments="$departments"/>

    <div class="mt-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            <strong class="font-medium">{{ $report['basis']->label() }}.</strong> {{ $report['basis']->description() }}
            This report recalculates from the ledger on the basis currently set; posted revenue share runs keep
            the basis they were posted under.
        </div>
    </div>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Total revenue" :value="\App\Support\Money::format($report['totals']['revenue'])" accent="#0A1F3D"
               :delta="$filters->label()"/>
        <x-kpi :label="$report['partner_name'].' share'" :value="\App\Support\Money::format($report['totals']['partner_share'])"
               accent="#00C2E8" :delta="rtrim(rtrim(number_format($report['percent'], 2), '0'), '.').'% of the base'"/>
        <x-kpi label="ALLVA share" :value="\App\Support\Money::format($report['totals']['allva_share'])" accent="#0E7C5A"
               delta="Before ALLVA’s own operating expenses"/>
    </div>

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th><th class="num">Revenue</th>
                        @if ($report['basis'] === \App\Enums\RevenueShareBasis::Net)
                            <th class="num">Operating expenses</th>
                        @endif
                        <th class="num">Base</th>
                        <th class="num">{{ $report['partner_name'] }}</th>
                        <th class="num">ALLVA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="num"><x-money :value="$row['revenue']"/></td>
                            @if ($report['basis'] === \App\Enums\RevenueShareBasis::Net)
                                <td class="num"><x-money :value="$row['operating_expenses']" muted/></td>
                            @endif
                            <td class="num"><x-money :value="$row['base']"/></td>
                            <td class="num"><x-money :value="$row['partner_share']"/></td>
                            <td class="num"><x-money :value="$row['allva_share']"/></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num"><x-money :value="$report['totals']['revenue']"/></td>
                        @if ($report['basis'] === \App\Enums\RevenueShareBasis::Net)
                            <td class="num"><x-money :value="$report['totals']['operating_expenses']"/></td>
                        @endif
                        <td class="num"><x-money :value="$report['totals']['base']"/></td>
                        <td class="num"><x-money :value="$report['totals']['partner_share']"/></td>
                        <td class="num"><x-money :value="$report['totals']['allva_share']"/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
