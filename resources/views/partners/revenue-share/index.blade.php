@extends('layouts.app')
@section('title', $partnerName.' revenue share')
@section('subtitle', strtoupper($basis->label().' · '.rtrim(rtrim(number_format($percent, 2), '0'), '.').'%'))

@section('content')
    {{-- Scope of work §5.1 and §10.1. The basis is shown prominently because it
         is the open decision that changes every partner's figure. --}}
    <div class="mb-4 flex items-start gap-2.5 rounded-md border border-line bg-white px-4 py-3 text-xs text-body">
        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
        <div class="leading-relaxed">
            <strong class="font-medium">{{ $basis->label() }}.</strong>
            {{ $basis->description() }}
            @can('manage-settings')
                <a href="{{ route('settings.edit') }}" class="text-signal underline">Change this in Settings</a> —
                a posted period keeps the basis it was posted under.
            @endcan
        </div>
    </div>

    <div class="mb-4 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Declared to date" :value="\App\Support\Money::format($totals['declared'])" accent="#0A1F3D"
               :delta="'Owed to '.$partnerName"/>
        <x-kpi label="Paid to date" :value="\App\Support\Money::format($totals['paid'])" accent="#0E7C5A" delta="Settled"/>
        <x-kpi label="Outstanding" :value="\App\Support\Money::format($totals['declared'] - $totals['paid'])"
               :accent="$totals['declared'] - $totals['paid'] > 0 ? '#9A6206' : '#98A2B3'" delta="Sitting in 2040"/>
        @if ($preview)
            <x-kpi label="{{ $currentPeriod->shortLabel() }} share so far"
                   :value="\App\Support\Money::format($preview['share_amount'])" accent="#00C2E8"
                   :delta="'On '.\App\Support\Money::format($preview['revenue_base']).' base'"/>
        @endif
    </div>

    @can('manage-revenue-share')
        @if ($currentPeriod && $preview && $preview['share_amount'] > 0)
            <form method="POST" action="{{ route('revenue-share.prepare') }}" class="card mb-4">
                @csrf
                <input type="hidden" name="period_id" value="{{ $currentPeriod->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="text-xs leading-relaxed text-body">
                        <span class="font-medium">{{ $currentPeriod->label() }}</span> —
                        gross revenue {{ \App\Support\Money::format($preview['gross_revenue'], true) }},
                        {{ $partnerName }}'s share {{ \App\Support\Money::format($preview['share_amount'], true) }},
                        leaving ALLVA {{ \App\Support\Money::format($preview['allva_share'], true) }}.
                    </div>
                    <button class="btn btn-primary btn-sm">Prepare this period</button>
                </div>
            </form>
        @endif
    @endcan

    <div class="card overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Revenue share runs</div>
                <div class="card-sub">One per period. The basis is frozen on each so a later change never restates it.</div>
            </div>
            <a href="{{ route('reports.revenue-split') }}" class="btn btn-secondary btn-sm">Revenue split report</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th><th>Period</th><th>Basis</th><th class="num">Gross revenue</th>
                        <th class="num">Base</th><th class="num">Share</th><th class="num">Paid</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('revenue-share.show', $run) }}" class="money text-xs hover:text-signal">
                                    {{ $run->reference }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap">{{ $run->period->label() }}</td>
                            <td>
                                <span class="badge badge-neutral">{{ $run->basis->label() }}</span>
                                <span class="eyebrow ms-1">{{ rtrim(rtrim(number_format($run->share_percent, 2), '0'), '.') }}%</span>
                            </td>
                            <td class="num"><x-money :value="$run->gross_revenue"/></td>
                            <td class="num"><x-money :value="$run->revenue_base"/></td>
                            <td class="num"><x-money :value="$run->share_amount"/></td>
                            <td class="num"><x-money :value="$run->paid_amount" muted/></td>
                            <td>
                                <x-badge :status="$run->status"
                                         :tone="match($run->status) { 'settled' => 'success', 'posted' => 'info', default => 'neutral' }"/>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No revenue share runs"
                            message="Scope of work §5.1 — 50% of project revenue goes to Cyber Gate and 50% to ALLVA."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($runs->hasPages())<div class="border-t border-rule px-5 py-3">{{ $runs->links() }}</div>@endif
    </div>
@endsection
