@extends('layouts.app')
@section('title', 'Invoices')
@section('subtitle', number_format($totals->count ?? 0).' INVOICES · '.\App\Support\Money::format($totals->outstanding ?? 0).' OUTSTANDING')

@section('actions')
    @can('manage-invoices')
        @if ($recognisable > 0)
            <form method="POST" action="{{ route('invoices.recognise') }}">
                @csrf
                <button class="btn btn-secondary">
                    <x-icon name="refresh" class="size-3.5"/> Recognise {{ $recognisable }} deferred
                </button>
            </form>
        @endif
        <a href="{{ route('invoices.generate') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Generate month
        </a>
    @endcan
@endsection

@section('content')
    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Invoiced" :value="\App\Support\Money::format($totals->invoiced ?? 0)" accent="#00C2E8"
               :delta="number_format($totals->count ?? 0).' invoices in this view'"/>
        <x-kpi label="Collected" :value="\App\Support\Money::format($totals->collected ?? 0)" accent="#0E7C5A"
               :delta="($totals->invoiced ?? 0) > 0
                    ? round(($totals->collected ?? 0) / $totals->invoiced * 100, 1).'% of what was invoiced'
                    : 'Nothing invoiced yet'"/>
        <x-kpi label="Outstanding" :value="\App\Support\Money::format($totals->outstanding ?? 0)"
               :accent="($totals->outstanding ?? 0) > 0 ? '#B3261E' : '#98A2B3'" delta="Still to collect"/>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="tabs">
            @foreach ([
                '' => 'All', 'draft' => 'Draft', 'outstanding' => 'Outstanding',
                'overdue' => 'Overdue', 'deferred' => 'Deferred', 'paid' => 'Paid',
            ] as $value => $label)
                <a href="{{ route('invoices.index', array_filter(request()->except('page', 'status') + ['status' => $value])) }}"
                   class="tab {{ $status === $value ? 'tab-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-2">
            @foreach (request()->except(['bus_company_id', 'month', 'q', 'page']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <input name="q" class="input w-[170px]" value="{{ request('q') }}" placeholder="Invoice number">
            <select name="bus_company_id" class="select w-[180px]">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('bus_company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <input type="month" name="month" class="input w-[150px]"
                   value="{{ request('month') ? \Illuminate\Support\Carbon::parse(request('month'))->format('Y-m') : '' }}">
            <button class="btn btn-secondary"><x-icon name="filter" class="size-3.5"/> Filter</button>
        </form>
    </div>

    @if ($status === 'draft' && $invoices->isNotEmpty())
        <form method="POST" action="{{ route('invoices.issue-all') }}"
              class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-signal/30 bg-[#E6F8FD] px-4 py-3">
            @csrf
            <input type="hidden" name="month" value="{{ $invoices->first()->billing_month->toDateString() }}">
            <div class="flex items-start gap-2.5 text-xs text-[#0A6E85]">
                <x-icon name="info" class="mt-px size-4 shrink-0"/>
                <span>
                    Draft invoices have not touched the ledger yet. Issuing posts them to receivables and revenue.
                </span>
            </div>
            @can('manage-invoices')
                <button class="btn btn-primary btn-sm">
                    Issue all for {{ $invoices->first()->billing_month->format('F Y') }}
                </button>
            @endcan
        </form>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Bus company</th>
                        <th>Month</th>
                        <th class="num">Students</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th class="num">Total</th>
                        <th class="num">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td>
                                <a href="{{ route('invoices.show', $invoice) }}" class="money text-xs hover:text-signal">
                                    {{ $invoice->number }}
                                </a>
                                @if ($invoice->is_deferred && ! $invoice->revenue_recognised)
                                    <div class="eyebrow !text-caution">Deferred</div>
                                @endif
                            </td>
                            <td>{{ $invoice->busCompany->name }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $invoice->billing_month->format('M Y') }}</td>
                            <td class="num">{{ number_format($invoice->student_count) }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $invoice->issue_date->format('j M') }}</td>
                            <td class="whitespace-nowrap {{ $invoice->isOverdue() ? 'text-negative' : 'text-muted' }}">
                                {{ $invoice->due_date->format('j M') }}
                                @if ($invoice->isOverdue())
                                    <div class="text-[11px]">{{ $invoice->daysOverdue() }}d overdue</div>
                                @endif
                            </td>
                            <td class="num"><x-money :value="$invoice->total"/></td>
                            <td class="num"><x-money :value="$invoice->balance_due" muted/></td>
                            <td><x-badge :status="$invoice->status"/></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty title="No invoices match this view"
                                         message="Scope of work §4.3: invoices are generated per bus company at the start or end of each month, from the registry.">
                                    @can('manage-invoices')
                                        <a href="{{ route('invoices.generate') }}" class="btn btn-primary btn-sm">Run billing</a>
                                    @endcan
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())<div class="border-t border-rule px-5 py-3">{{ $invoices->links() }}</div>@endif
    </div>
@endsection
