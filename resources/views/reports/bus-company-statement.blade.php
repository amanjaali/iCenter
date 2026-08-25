@extends('layouts.app')
@section('title', 'Statement — '.$report['company']->name)
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header :title="'Statement — '.$report['company']->name" :filters="$filters"/>

    <x-page-filters :action="route('reports.bus-company-statement', $report['company'])" :filters="$filters"/>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-4">
        <x-kpi label="Buses registered" :value="number_format($report['totals']['buses'])" accent="#0A1F3D"
               :delta="number_format($report['totals']['students']).' students'"/>
        <x-kpi label="Invoiced" :value="\App\Support\Money::format($report['totals']['invoiced'])" accent="#00C2E8"
               :delta="$filters->label()"/>
        <x-kpi label="Collected" :value="\App\Support\Money::format($report['totals']['collected'])" accent="#0E7C5A"
               delta="Received in the period"/>
        <x-kpi label="Balance outstanding" :value="\App\Support\Money::format($report['closing_balance'])"
               :accent="$report['closing_balance'] > 0 ? '#B3261E' : '#98A2B3'" delta="Carried at the period end"/>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_300px]">
        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Account movement</div>
                    <div class="card-sub">Invoices raised and receipts applied, in date order.</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr><th>Date</th><th>Reference</th><th>Description</th>
                            <th class="num">Charged</th><th class="num">Paid</th><th class="num">Balance</th></tr>
                    </thead>
                    <tbody>
                        <tr class="bg-mist">
                            <td colspan="5" class="text-muted">Opening balance</td>
                            <td class="num"><x-money :value="$report['opening_balance']"/></td>
                        </tr>
                        @forelse ($report['entries'] as $entry)
                            <tr>
                                <td class="whitespace-nowrap text-muted">{{ $entry->date->format('j M Y') }}</td>
                                <td>
                                    <a href="{{ $entry->type === 'invoice'
                                        ? route('invoices.show', $entry->model)
                                        : route('payments.show', $entry->model) }}"
                                       class="money text-xs hover:text-signal">{{ $entry->reference }}</a>
                                </td>
                                <td class="text-muted">{{ $entry->description }}</td>
                                <td class="num">{{ $entry->debit > 0 ? \App\Support\Money::format($entry->debit) : '' }}</td>
                                <td class="num">{{ $entry->credit > 0 ? \App\Support\Money::format($entry->credit) : '' }}</td>
                                <td class="num"><x-money :value="$entry->balance"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty title="No activity in this period"/></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">Closing balance</td>
                            <td class="num"><x-money :value="$report['closing_balance']"/></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden self-start">
            <div class="card-head">
                <div>
                    <div class="card-title">Buses and students</div>
                    <div class="card-sub">As at {{ $filters->to->format('F Y') }}</div>
                </div>
            </div>
            <table class="table table-compact">
                <thead><tr><th>Bus</th><th class="num">Students</th></tr></thead>
                <tbody>
                    @foreach ($report['buses'] as $row)
                        <tr>
                            <td>
                                <a href="{{ route('buses.show', $row['bus']) }}" class="hover:text-signal">{{ $row['bus']->code }}</a>
                                @if ($row['bus']->route_name)<div class="eyebrow">{{ $row['bus']->route_name }}</div>@endif
                            </td>
                            <td class="num">{{ number_format($row['students']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td>{{ $report['totals']['buses'] }} buses</td>
                        <td class="num">{{ number_format($report['totals']['students']) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
