@extends('layouts.app')
@section('title', 'Generate monthly billing')
@section('subtitle', strtoupper($month->format('F Y')))

@section('content')
    {{-- Scope of work §4.3: billing is generated from the registry. This screen
         is a dry run first — the accountant sees exactly what will be raised,
         and why a company is being skipped, before anything is written. --}}

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[170px]">
            <label class="label" for="month">Billing month</label>
            <input id="month" name="month" type="month" class="input" value="{{ $month->format('Y-m') }}">
        </div>
        <button class="btn btn-secondary"><x-icon name="refresh" class="size-3.5"/> Recalculate</button>

        <div class="ms-auto flex flex-col items-end gap-1">
            <span class="eyebrow">Mid-month rule in force</span>
            <span class="text-xs text-body">{{ $prorationMethod->label() }}</span>
        </div>
    </form>

    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Companies to bill" :value="number_format($billableCount)" accent="#00C2E8"
               :delta="$previews->count().' active companies checked'"/>
        <x-kpi label="Total to invoice" :value="\App\Support\Money::format($billableTotal)" accent="#0A1F3D"
               :delta="$month->format('F Y')"/>
        <x-kpi label="Already invoiced" :value="number_format($previews->filter(fn($r) => $r['existing'])->count())"
               accent="#98A2B3" delta="Skipped — one invoice per company per month"/>
    </div>

    <form method="POST" action="{{ route('invoices.run-generation') }}">
        @csrf
        <input type="hidden" name="month" value="{{ $month->toDateString() }}">

        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">What will be billed</div>
                    <div class="card-sub">
                        Taken from the enrollment history: bus company → buses → students present in the month.
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-muted">
                        <input type="checkbox" data-select-all="#generate-rows" checked class="size-3.5 accent-navy">
                        Select all
                    </label>
                </div>
            </div>

            <div id="generate-rows" class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th>Bus company</th>
                            <th class="num">Buses</th>
                            <th class="num">Students</th>
                            <th class="num">Billable months</th>
                            <th class="num">Rate</th>
                            <th class="num">Amount</th>
                            <th>Outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previews as $row)
                            @php
                                $preview = $row['preview'];
                                $billable = $preview['billable'] ?? false;
                            @endphp
                            <tr class="{{ $billable ? '' : 'opacity-60' }}">
                                <td>
                                    @if ($billable)
                                        <input type="checkbox" name="company_ids[]" value="{{ $row['company']->id }}"
                                               checked class="size-3.5 accent-navy">
                                    @endif
                                </td>
                                <td>
                                    <span class="font-medium">{{ $row['company']->name }}</span>
                                    <div class="eyebrow">{{ $row['company']->code }}</div>
                                </td>
                                <td class="num">{{ $billable ? count($preview['lines']) : '—' }}</td>
                                <td class="num">{{ $billable ? number_format($preview['student_count']) : '—' }}</td>
                                <td class="num">{{ $billable ? number_format($preview['billable_units'], 2) : '—' }}</td>
                                <td class="num">{{ $billable ? \App\Support\Money::format($preview['rate']) : '—' }}</td>
                                <td class="num">
                                    @if ($billable)
                                        <x-money :value="$preview['total']"/>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($row['existing'])
                                        <a href="{{ route('invoices.show', $row['existing']) }}" class="badge badge-info">
                                            {{ $row['existing']->number }}
                                        </a>
                                    @elseif ($billable)
                                        <span class="badge badge-success">Will be billed</span>
                                    @else
                                        <span class="text-[11px] leading-relaxed text-muted">{{ $preview['reason'] }}</span>
                                    @endif
                                </td>
                            </tr>

                            @if ($billable)
                                {{-- The per-bus breakdown, so the figure is auditable before it is committed. --}}
                                @foreach ($preview['lines'] as $line)
                                    <tr class="bg-mist/60">
                                        <td></td>
                                        <td class="ps-8 text-[11.5px] text-muted">{{ $line['bus_label'] }}</td>
                                        <td class="num"></td>
                                        <td class="num text-[11.5px] text-muted">{{ $line['student_count'] }}</td>
                                        <td class="num text-[11.5px] text-muted">{{ number_format($line['billable_units'], 2) }}</td>
                                        <td class="num text-[11.5px] text-muted">{{ \App\Support\Money::format($line['unit_price']) }}</td>
                                        <td class="num text-[11.5px] text-muted">{{ \App\Support\Money::format($line['line_total']) }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Total to invoice for {{ $month->format('F Y') }}</td>
                            <td class="num"><x-money :value="$billableTotal"/></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-rule px-5 py-4">
                <div class="flex items-center gap-3">
                    <label class="label !mb-0" for="issue_date">Issue date</label>
                    <input id="issue_date" name="issue_date" type="date" class="input w-[160px]"
                           value="{{ $month->copy()->endOfMonth()->toDateString() }}">
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('invoices.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" @disabled($billableCount === 0)>
                        Generate {{ $billableCount }} draft {{ str('invoice')->plural($billableCount) }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    <p class="mt-4 max-w-3xl text-[11px] leading-relaxed text-faint">
        Generation creates drafts only. Nothing reaches the ledger until the invoices are issued,
        and a month that has already been invoiced is skipped rather than billed twice.
    </p>
@endsection
