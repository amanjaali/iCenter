@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', $period ? strtoupper($period->label().' · '.$period->status->value) : '')

@section('actions')
    @can('manage-invoices')
        <a href="{{ route('invoices.generate') }}" class="btn btn-secondary">
            <x-icon name="refresh" class="size-3.5"/> Run billing
        </a>
    @endcan
    @can('post-entries')
        <a href="{{ route('journals.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> New journal
        </a>
    @endcan
@endsection

@section('content')
    @php
        $ytdMargin = $yearToDate['revenue'] > 0
            ? round($yearToDate['net_profit'] / $yearToDate['revenue'] * 100, 1)
            : 0;
    @endphp

    {{-- KPIs ---------------------------------------------------------- --}}
    <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi
            label="Revenue this month"
            :value="\App\Support\Money::format($thisMonth['revenue'])"
            :delta="$revenueChange === null ? 'No comparison available' : abs($revenueChange).'% against last month'"
            :direction="$revenueChange === null ? null : ($revenueChange >= 0 ? 'up' : 'down')"
            accent="#00C2E8"
            :href="route('reports.income-statement')"
        />
        <x-kpi
            label="Net profit this month"
            :value="\App\Support\Money::format($thisMonth['net_profit'])"
            :delta="$profitChange === null ? 'No comparison available' : abs($profitChange).'% against last month'"
            :direction="$profitChange === null ? null : ($profitChange >= 0 ? 'up' : 'down')"
            accent="#0A1F3D"
            :href="route('reports.income-statement')"
        />
        <x-kpi
            label="Cash and bank"
            :value="\App\Support\Money::format($cash)"
            delta="Across all cash and bank accounts"
            accent="#0E7C5A"
            :href="route('reports.cash-flow')"
        />
        <x-kpi
            label="Outstanding receivables"
            :value="\App\Support\Money::format($receivables['total'])"
            :delta="$receivables['bands']['over_90']['amount'] > 0
                ? \App\Support\Money::format($receivables['bands']['over_90']['amount']).' over 90 days'
                : 'Nothing over 90 days'"
            :accent="$receivables['bands']['over_90']['amount'] > 0 ? '#B3261E' : '#98A2B3'"
            :href="route('reports.receivables-ageing')"
        />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_340px]">

        {{-- Cash and trading chart -------------------------------------- --}}
        <div class="card flex flex-col">
            <div class="card-head">
                <div>
                    <div class="card-title">Cash position</div>
                    <div class="card-sub">Last 12 months · {{ config('allva.currency.code') }}</div>
                </div>
                <a href="{{ route('reports.cash-flow') }}" class="btn btn-secondary btn-sm">Cash flow</a>
            </div>

            <div class="flex-1 p-5">
                @include('dashboard.partials.cash-chart', ['series' => $cashSeries])
            </div>

            <div class="flex flex-wrap items-center gap-5 border-t border-rule px-5 py-3">
                <span class="flex items-center gap-2">
                    <span class="h-[2.5px] w-3.5 bg-navy"></span>
                    <span class="text-[11px] text-muted">Cash held</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-[2.5px] w-3.5 bg-signal"></span>
                    <span class="text-[11px] text-muted">Revenue</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-[2.5px] w-3.5" style="background: #C7D0DC"></span>
                    <span class="text-[11px] text-muted">Costs</span>
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            {{-- Ageing ------------------------------------------------- --}}
            <div class="card">
                <div class="card-head">
                    <div class="card-title">Receivables ageing</div>
                    <a href="{{ route('reports.receivables-ageing') }}" class="text-[11px] text-muted hover:text-signal">View</a>
                </div>
                <div class="flex flex-col gap-3 p-5">
                    @forelse ($receivables['bands'] as $key => $band)
                        @continue($band['amount'] <= 0)
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[11.5px] text-muted">{{ $band['label'] }}</span>
                                <x-money :value="$band['amount']" class="text-[11.5px]"/>
                            </div>
                            <div class="h-[5px] rounded-sm bg-rule">
                                <div class="h-[5px] rounded-sm"
                                     style="width: {{ $band['percent'] }}%; background: {{ $key === 'over_90' ? '#B3261E' : ($key === 'current' ? '#0E7C5A' : '#00C2E8') }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-faint">Nothing is outstanding.</p>
                    @endforelse
                </div>
            </div>

            {{-- Needs attention ---------------------------------------- --}}
            <div class="flex flex-1 flex-col gap-3.5 rounded-md bg-navy p-5">
                <div class="font-display text-sm font-medium text-white">Needs attention</div>

                @forelse ($attention as $item)
                    <a href="{{ $item['href'] }}" class="group flex items-start gap-2.5">
                        <span class="mt-1.5 size-[5px] shrink-0 rounded-full bg-signal-light"></span>
                        <span class="flex flex-col gap-0.5">
                            <span class="text-xs text-white group-hover:text-signal-light">{{ $item['title'] }}</span>
                            <span class="text-[10.5px] text-dark-muted">{{ $item['meta'] }}</span>
                        </span>
                    </a>
                @empty
                    <div class="flex items-start gap-2.5">
                        <x-icon name="check" class="mt-px size-4 shrink-0 text-signal-light"/>
                        <span class="text-xs text-dark-text">Everything is up to date for {{ now()->format('F') }}.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Year to date + partner position -------------------------------- --}}
    <div class="mt-4 grid items-start gap-4 lg:grid-cols-3">
        <div class="card card-pad">
            <div class="eyebrow mb-3">Year to date</div>
            <x-stat-row label="Revenue" :value="$yearToDate['revenue']"/>
            <x-stat-row label="Direct costs" :value="-$yearToDate['direct_costs']" indent/>
            <x-stat-row label="Gross profit" :value="$yearToDate['gross_profit']" emphasis/>
            <x-stat-row label="Operating expenses" :value="-$yearToDate['operating_expenses']" indent/>
            <x-stat-row label="Net profit" :value="$yearToDate['net_profit']" emphasis/>
            <div class="mt-2 flex items-center justify-between border-t border-rule pt-2.5">
                <span class="eyebrow">Net margin</span>
                <span class="money text-xs {{ $ytdMargin < 0 ? 'money-negative' : '' }}">{{ $ytdMargin }}%</span>
            </div>
        </div>

        <div class="card card-pad">
            <div class="eyebrow mb-3">Partner position · year to date</div>
            <x-stat-row label="Net profit" :value="$partnerShare['net_profit']"/>
            <x-stat-row label="Distributable" :value="$partnerShare['distributable']" emphasis/>
            <div class="mt-3 flex flex-col gap-2 border-t border-rule pt-3">
                @foreach ($partnerShare['shares'] as $share)
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full bg-mist
                                         font-display text-[10px] font-medium text-navy">
                                {{ $share['partner']->initials() }}
                            </span>
                            <span class="truncate text-xs text-body">{{ $share['partner']->name }}</span>
                            <span class="eyebrow shrink-0">{{ rtrim(rtrim(number_format($share['percent'], 2), '0'), '.') }}%</span>
                        </span>
                        <x-money :value="$share['amount']" class="shrink-0 text-xs"/>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('distributions.index') }}" class="btn btn-secondary btn-sm mt-4 w-full justify-center">
                Distributions
            </a>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-title">Recent invoices</div>
                <a href="{{ route('invoices.index') }}" class="text-[11px] text-muted hover:text-signal">All</a>
            </div>
            <div class="flex flex-col">
                @forelse ($recentInvoices as $invoice)
                    <a href="{{ route('invoices.show', $invoice) }}"
                       class="flex items-center justify-between gap-3 border-b border-rule px-5 py-2.5 last:border-0 hover:bg-mist">
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="truncate text-xs text-ink">{{ $invoice->busCompany->name }}</span>
                            <span class="eyebrow">{{ $invoice->number }} · {{ $invoice->billing_month->format('M Y') }}</span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <x-money :value="$invoice->total" class="text-xs"/>
                            <x-badge :status="$invoice->status"/>
                        </span>
                    </a>
                @empty
                    <div class="px-5 py-6 text-xs text-faint">No invoices have been issued yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-3">
        <div class="card flex items-center justify-between p-4">
            <span class="eyebrow">Active students</span>
            <span class="font-display text-lg font-medium">{{ number_format($activeStudents) }}</span>
        </div>
        <div class="card flex items-center justify-between p-4">
            <span class="eyebrow">Bus companies</span>
            <span class="font-display text-lg font-medium">{{ number_format($busCompanies) }}</span>
        </div>
        <div class="card flex items-center justify-between p-4">
            <span class="eyebrow">{{ $sharePartner }} payable</span>
            <x-money :value="$sharePayable" class="font-display text-lg font-medium"/>
        </div>
    </div>
@endsection
