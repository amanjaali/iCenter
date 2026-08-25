@extends('layouts.app')
@section('title', $partner->name)
@section('subtitle', $partner->code.' · '.rtrim(rtrim(number_format($partner->ownership_percent, 4), '0'), '.').'% OWNERSHIP')

@section('actions')
    @can('manage-partners')
        <a href="{{ route('partners.edit', $partner) }}" class="btn btn-primary">Edit</a>
    @endcan
@endsection

@section('content')
    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Profit share declared" :value="\App\Support\Money::format($declared)" accent="#0A1F3D"
               delta="Across all distributions"/>
        <x-kpi label="Paid out" :value="\App\Support\Money::format($paid)" accent="#0E7C5A" delta="Settled to date"/>
        <x-kpi label="Still outstanding" :value="\App\Support\Money::format($outstanding)"
               :accent="$outstanding > 0 ? '#9A6206' : '#98A2B3'" delta="Declared but not yet paid"/>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            <div class="card overflow-hidden">
                <div class="card-head">
                    <div>
                        <div class="card-title">Distributions</div>
                        <div class="card-sub">
                            Each row shows exactly which months the share relates to (scope of work §5.2).
                        </div>
                    </div>
                </div>
                <table class="table table-compact">
                    <thead>
                        <tr><th>Distribution</th><th>Period covered</th><th class="num">Share %</th>
                            <th class="num">Declared</th><th class="num">Paid</th><th class="num">Outstanding</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($partner->distributionLines->sortByDesc(fn ($l) => $l->run->period_end) as $line)
                            <tr>
                                <td>
                                    <a href="{{ route('distributions.show', $line->run) }}" class="money text-xs hover:text-signal">
                                        {{ $line->run->reference }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $line->run->periodLabel() }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format($line->ownership_percent, 4), '0'), '.') }}%</td>
                                <td class="num"><x-money :value="$line->share_amount"/></td>
                                <td class="num"><x-money :value="$line->paid_amount" muted/></td>
                                <td class="num"><x-money :value="$line->outstanding_amount" muted/></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty title="No distributions yet"
                                message="Profit is distributed once a distribution run has been declared and posted."/></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($partner->payouts->isNotEmpty())
                <div class="card">
                    <div class="card-head"><div class="card-title">Payments received</div></div>
                    <table class="table table-compact">
                        <thead><tr><th>Reference</th><th>Date</th><th>From</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($partner->payouts->sortByDesc('payout_date') as $payout)
                                <tr>
                                    <td class="money text-xs">{{ $payout->reference }}</td>
                                    <td class="text-muted">{{ $payout->payout_date->format('j M Y') }}</td>
                                    <td class="text-muted">{{ $payout->sourceAccount->displayName() }}</td>
                                    <td class="num"><x-money :value="$payout->amount"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($partner->ownershipChanges->isNotEmpty())
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">Ownership changes</div>
                            <div class="card-sub">Scope of work §9.3 — logged with the user, the date and the reason.</div>
                        </div>
                    </div>
                    <table class="table table-compact">
                        <thead><tr><th>Effective</th><th class="num">From</th><th class="num">To</th><th>By</th><th>Reason</th></tr></thead>
                        <tbody>
                            @foreach ($partner->ownershipChanges as $change)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $change->effective_date->format('j M Y') }}</td>
                                    <td class="num">{{ rtrim(rtrim(number_format($change->old_percent, 4), '0'), '.') }}%</td>
                                    <td class="num">{{ rtrim(rtrim(number_format($change->new_percent, 4), '0'), '.') }}%</td>
                                    <td class="text-muted">{{ $change->user?->name ?? 'System' }}</td>
                                    <td class="text-muted">{{ $change->reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card card-pad self-start">
            <div class="eyebrow mb-3">Partner</div>
            <dl class="flex flex-col gap-2.5 text-xs">
                @foreach ([
                    'Code' => $partner->code,
                    'Email' => $partner->email,
                    'Phone' => $partner->phone,
                    'Login' => $partner->user?->email,
                    'Capital account' => $partner->capitalAccount?->displayName(),
                    'Joined' => $partner->joined_on?->format('j M Y'),
                    'Left' => $partner->left_on?->format('j M Y'),
                ] as $label => $value)
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-faint">{{ $label }}</dt>
                        <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
@endsection
