@extends('layouts.app')
@section('title', 'Partner distributions')
@section('subtitle', 'SCOPE OF WORK §5.2 · NET PROFIT AFTER ALL EXPENSES')

@section('actions')
    @can('manage-distributions')
        <a href="{{ route('distributions.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> New distribution
        </a>
    @endcan
@endsection

@section('content')
    @if (abs($totalOwnership - 100) > 0.0001)
        <div class="mb-4 flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
            <x-icon name="alert" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                Partner ownership totals {{ rtrim(rtrim(number_format($totalOwnership, 4), '0'), '.') }}%.
                Correct it on the <a href="{{ route('partners.index') }}" class="underline">Partners</a> screen
                before declaring a distribution.
            </div>
        </div>
    @endif

    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Declared to date" :value="\App\Support\Money::format($totals['declared'])" accent="#0A1F3D"
               delta="Across all posted distributions"/>
        <x-kpi label="Paid to partners" :value="\App\Support\Money::format($totals['paid'])" accent="#0E7C5A" delta="Settled"/>
        <x-kpi label="Still outstanding" :value="\App\Support\Money::format($totals['declared'] - $totals['paid'])"
               :accent="$totals['declared'] - $totals['paid'] > 0 ? '#9A6206' : '#98A2B3'" delta="Sitting in 2050"/>
    </div>

    <div class="card overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Distributions</div>
                <div class="card-sub">
                    Each covers an explicit date range, so a partner can see exactly which months a payment covers.
                </div>
            </div>
            <a href="{{ route('reports.partner-distribution') }}" class="btn btn-secondary btn-sm">Distribution report</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th><th>Period covered</th><th class="num">Net profit</th>
                        <th class="num">Retained</th><th class="num">Distributed</th>
                        <th class="num">Paid out</th><th class="num">Partners</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('distributions.show', $run) }}" class="money text-xs hover:text-signal">
                                    {{ $run->reference }}
                                </a>
                                @if ($run->title)<div class="eyebrow">{{ $run->title }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $run->periodLabel() }}</td>
                            <td class="num"><x-money :value="$run->net_profit"/></td>
                            <td class="num"><x-money :value="$run->retained_amount" muted/></td>
                            <td class="num"><x-money :value="$run->distributable_amount"/></td>
                            <td class="num"><x-money :value="$run->distributed_amount" muted/></td>
                            <td class="num">{{ $run->lines->count() }}</td>
                            <td>
                                <x-badge :status="$run->status"
                                         :tone="match($run->status) { 'settled' => 'success', 'posted' => 'info', default => 'neutral' }"/>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No distributions declared"
                            message="Scope of work §5.2 — the remaining net profit is distributed among the five partners, 20% to each."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($runs->hasPages())<div class="border-t border-rule px-5 py-3">{{ $runs->links() }}</div>@endif
    </div>
@endsection
