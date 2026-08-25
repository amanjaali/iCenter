@extends('layouts.app')
@section('title', 'Trial balance')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Trial balance" :filters="$filters"/>

    <x-page-filters :action="route('reports.trial-balance')" :filters="$filters"
                    :projects="$projects" :departments="$departments"/>

    <div class="card mt-4 overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Posted movement by account</div>
                <div class="card-sub">Net of debits and credits, shown on one side only.</div>
            </div>
            @if ($report['balanced'])
                <span class="badge badge-success"><x-icon name="check" class="size-3"/> Balanced</span>
            @else
                <span class="badge badge-danger">Out by {{ \App\Support\Money::format(abs($report['total_debit'] - $report['total_credit'])) }}</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr><th class="w-20">Code</th><th>Account</th><th>Type</th>
                        <th class="num">Debit</th><th class="num">Credit</th></tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td><a href="{{ route('accounts.show', $row->id) }}" class="money text-xs hover:text-signal">{{ $row->code }}</a></td>
                            <td>{{ $row->name }}</td>
                            <td class="text-muted">{{ ucfirst($row->type) }}</td>
                            <td class="num">{{ $row->net_debit > 0 ? \App\Support\Money::format($row->net_debit) : '' }}</td>
                            <td class="num">{{ $row->net_credit > 0 ? \App\Support\Money::format($row->net_credit) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty title="Nothing posted in this period"/></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Totals</td>
                        <td class="num"><x-money :value="$report['total_debit']"/></td>
                        <td class="num"><x-money :value="$report['total_credit']"/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
