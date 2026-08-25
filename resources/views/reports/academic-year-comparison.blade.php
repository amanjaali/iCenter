@extends('layouts.app')
@section('title', 'Academic year comparison')
@section('subtitle', 'ONE YEAR AGAINST THE NEXT')

@section('content')
    <x-report-header title="Academic year comparison"/>

    <form method="GET" class="card flex items-center justify-end gap-2 p-3.5 no-print">
        <button type="submit" name="export" value="csv" class="btn btn-secondary">
            <x-icon name="download" class="size-3.5"/> CSV
        </button>
        <button type="button" onclick="window.print()" class="btn btn-secondary">
            <x-icon name="print" class="size-3.5"/> Print
        </button>
    </form>

    @unless ($report['comparable'])
        <div class="mt-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
            <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
            <div class="leading-relaxed">
                Scope of work §8.2 asks for this comparison "once a second year exists". Only one academic year
                has been billed so far, so there is nothing to compare against yet.
            </div>
        </div>
    @endunless

    <div class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Academic year</th><th class="num">Standard rate</th><th class="num">Months billed</th>
                        <th class="num">Average students</th><th class="num">Peak students</th>
                        <th class="num">Invoiced</th><th class="num">Collected</th>
                        <th class="num">Outstanding</th><th class="num">Revenue growth</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td class="font-medium">{{ $row['year']->name }}</td>
                            <td class="num">{{ $row['standard_rate'] > 0 ? \App\Support\Money::format($row['standard_rate']) : '—' }}</td>
                            <td class="num">{{ $row['months_billed'] }}</td>
                            <td class="num">{{ number_format($row['average_students']) }}</td>
                            <td class="num">{{ number_format($row['peak_students']) }}</td>
                            <td class="num"><x-money :value="$row['invoiced']"/></td>
                            <td class="num"><x-money :value="$row['collected']"/></td>
                            <td class="num"><x-money :value="$row['outstanding']" muted/></td>
                            <td class="num">
                                @if ($row['revenue_growth'] === null)
                                    <span class="text-faint">—</span>
                                @else
                                    <span class="{{ $row['revenue_growth'] >= 0 ? 'text-positive' : 'text-negative' }}">
                                        {{ $row['revenue_growth'] >= 0 ? '+' : '' }}{{ $row['revenue_growth'] }}%
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty title="No academic years billed yet"/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
