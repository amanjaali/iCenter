@extends('layouts.app')
@section('title', 'Partners')
@section('subtitle', 'SCOPE OF WORK §2 · OWNERSHIP IS CONFIGURABLE')

@section('actions')
    @can('manage-partners')
        <a href="{{ route('partners.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Add partner</a>
    @endcan
@endsection

@section('content')
    @if (abs($totalOwnership - 100) > 0.0001)
        <div class="mb-4 flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
            <x-icon name="alert" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                Ownership totals {{ rtrim(rtrim(number_format($totalOwnership, 4), '0'), '.') }}%, not 100%.
                Distributions still allocate the whole distributable amount by relative weight, so nothing is
                lost — but the shares will not be the percentages shown until this is corrected.
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Ownership</div>
                <div class="card-sub">
                    Percentages are data, not constants — a change in shareholding needs no rebuild,
                    and every change is logged with a reason.
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Partner</th><th>Login</th><th>Capital account</th>
                        <th class="num">Ownership</th><th class="num">Declared</th>
                        <th class="num">Outstanding</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partners as $partner)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <span class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-mist
                                                 font-display text-[11px] font-medium text-navy">
                                        {{ $partner->initials() }}
                                    </span>
                                    <span>
                                        <a href="{{ route('partners.show', $partner) }}" class="font-medium hover:text-signal">
                                            {{ $partner->name }}
                                        </a>
                                        <div class="eyebrow">{{ $partner->code }}</div>
                                    </span>
                                </div>
                            </td>
                            <td class="text-muted">{{ $partner->user?->email ?? '—' }}</td>
                            <td class="text-muted">
                                @if ($partner->capitalAccount)
                                    <span class="money">{{ $partner->capitalAccount->code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">{{ rtrim(rtrim(number_format($partner->ownership_percent, 4), '0'), '.') }}%</td>
                            <td class="num"><x-money :value="$declared[$partner->id] ?? 0" muted/></td>
                            <td class="num"><x-money :value="$outstanding[$partner->id] ?? 0" muted/></td>
                            <td><x-badge :tone="$partner->is_active ? 'success' : 'neutral'" :label="$partner->is_active ? 'Active' : 'Inactive'"/></td>
                            <td class="text-end">
                                @can('manage-partners')
                                    <a href="{{ route('partners.edit', $partner) }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="edit" class="size-3.5"/>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No partners"
                            message="Scope of work §2 — ALLVA has five partners, each holding 20%."/></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num {{ abs($totalOwnership - 100) > 0.0001 ? 'text-negative' : '' }}">
                            {{ rtrim(rtrim(number_format($totalOwnership, 4), '0'), '.') }}%
                        </td>
                        <td class="num"><x-money :value="$declared->sum()"/></td>
                        <td class="num"><x-money :value="$outstanding->sum()"/></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
