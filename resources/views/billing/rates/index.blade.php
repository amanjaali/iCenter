@extends('layouts.app')
@section('title', 'Rate table')
@section('subtitle', 'PER STUDENT, PER MONTH · EFFECTIVE DATED')

@section('actions')
    @can('manage-rates')
        <a href="{{ route('rates.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Add rate</a>
    @endcan
@endsection

@section('content')
    {{-- Scope of work §4.2: the rate must never be hardcoded, historical months
         keep the rate that applied at the time, and every change is logged with
         the user, the date and the reason. --}}

    <div class="card mb-4">
        <div class="card-head">
            <div>
                <div class="card-title">In force today</div>
                <div class="card-sub">What each active bus company is billed at right now.</div>
            </div>
        </div>
        <div class="grid gap-px bg-rule sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($inForce as $row)
                <div class="bg-white p-4">
                    <div class="text-xs font-medium">{{ $row['company']->name }}</div>
                    @if ($row['rate'])
                        <div class="mt-1.5 font-display text-xl font-medium">
                            {{ \App\Support\Money::format($row['rate']->amount) }}
                            <span class="text-[11px] font-normal text-faint">{{ config('allva.currency.code') }}</span>
                        </div>
                        <div class="eyebrow mt-1">
                            {{ $row['rate']->isGeneral() ? 'Standard rate' : 'Special agreement' }}
                        </div>
                    @else
                        <div class="mt-1.5 text-xs text-negative">No rate in force — billing will be skipped</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="card mb-4 overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Rate table</div>
                <div class="card-sub">
                    A new rate for a future year is added, never edited over the old one — that is what
                    keeps historical months billed correctly.
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Academic year</th>
                        <th>Applies to</th>
                        <th class="num">Amount</th>
                        <th>Effective from</th>
                        <th>Effective to</th>
                        <th>Set by</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rates as $rate)
                        <tr class="{{ $rate->is_active ? '' : 'opacity-55' }}">
                            <td>{{ $rate->academicYear?->name ?? '—' }}</td>
                            <td>
                                @if ($rate->isGeneral())
                                    <span class="text-muted">All bus companies</span>
                                @else
                                    <a href="{{ route('bus-companies.show', $rate->bus_company_id) }}" class="hover:text-signal">
                                        {{ $rate->busCompany->name }}
                                    </a>
                                    <div class="eyebrow">Special agreement</div>
                                @endif
                            </td>
                            <td class="num"><x-money :value="$rate->amount"/></td>
                            <td class="whitespace-nowrap text-muted">{{ $rate->effective_from->format('j M Y') }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $rate->effective_to?->format('j M Y') ?? 'Open ended' }}</td>
                            <td class="text-muted">{{ $rate->createdBy?->name ?? 'System' }}</td>
                            <td>
                                <x-badge :tone="$rate->is_active ? 'success' : 'neutral'"
                                         :label="$rate->is_active ? 'Active' : 'Retired'"/>
                            </td>
                            <td class="text-end">
                                @can('manage-rates')
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('rates.edit', $rate) }}" class="btn btn-ghost btn-sm">
                                            <x-icon name="edit" class="size-3.5"/>
                                        </a>
                                        @if ($rate->is_active)
                                            <x-confirm-form :action="route('rates.deactivate', $rate)" reason
                                                reason-label="Why is this rate being retired?"
                                                confirm="Retiring a rate stops it applying to future months. Invoices already raised at this rate are unaffected."
                                                class="btn btn-ghost btn-sm">
                                                <x-icon name="x" class="size-3.5"/>
                                            </x-confirm-form>
                                        @endif
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No rates configured"
                            message="Scope of work §4.1: 4,000 IQD per student per month for 2026–2027. Without a rate covering a month, that month cannot be billed."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">Change log</div>
                <div class="card-sub">Scope of work §4.2 — every rate change carries the user, the date and the reason.</div>
            </div>
            <a href="{{ route('reports.rate-history') }}" class="btn btn-secondary btn-sm">Rate history report</a>
        </div>
        <table class="table table-compact">
            <thead>
                <tr><th>When</th><th>Who</th><th>Action</th><th class="num">From</th><th class="num">To</th><th>Reason</th></tr>
            </thead>
            <tbody>
                @forelse ($changes as $change)
                    <tr>
                        <td class="whitespace-nowrap text-muted">{{ $change->created_at->format('j M Y H:i') }}</td>
                        <td>{{ $change->user?->name ?? $change->user_name ?? 'System' }}</td>
                        <td><x-badge :label="ucfirst($change->action)" tone="info"/></td>
                        <td class="num">{{ $change->old_amount ? \App\Support\Money::format($change->old_amount) : '—' }}</td>
                        <td class="num">{{ $change->new_amount ? \App\Support\Money::format($change->new_amount) : '—' }}</td>
                        <td class="text-muted">{{ $change->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-faint">No changes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
