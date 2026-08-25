@extends('layouts.app')
@section('title', 'Receivables ageing')
@section('subtitle', 'AS AT '.strtoupper($asAt->format('j F Y')))

@section('content')
    <x-report-header title="Receivables ageing" :subtitle="'As at '.$asAt->format('j F Y')"/>

    <form method="GET" class="card flex flex-wrap items-end gap-3 p-3.5 no-print">
        <div class="min-w-[150px]">
            <label class="label" for="as_at">As at</label>
            <input id="as_at" name="as_at" type="date" class="input" value="{{ $asAt->toDateString() }}">
        </div>
        <div class="min-w-[220px]">
            <label class="label" for="bus_company_id">Bus company</label>
            <select id="bus_company_id" name="bus_company_id" class="select">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('bus_company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Apply</button>
        <div class="ms-auto flex items-center gap-2">
            <button type="submit" name="export" value="csv" class="btn btn-secondary">
                <x-icon name="download" class="size-3.5"/> CSV
            </button>
            <button type="button" onclick="window.print()" class="btn btn-secondary">
                <x-icon name="print" class="size-3.5"/> Print
            </button>
        </div>
    </form>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ($report['bands'] as $key => $band)
            <x-kpi :label="$band['label']" :value="\App\Support\Money::format($band['amount'])"
                   :accent="$key === 'over_90' ? '#B3261E' : ($key === 'current' ? '#0E7C5A' : '#00C2E8')"
                   :delta="$band['percent'].'% of the total · '.$band['invoices']->count().' invoices'"/>
        @endforeach
    </div>

    @if (abs($report['total'] - $report['ledger_receivable']) > 0.5)
        <div class="mt-4 flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
            <x-icon name="alert" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                The invoices above total {{ \App\Support\Money::format($report['total'], true) }}, but account 1030
                shows {{ \App\Support\Money::format($report['ledger_receivable'], true) }}. A difference usually means
                a receipt was posted without being applied to an invoice, or a manual journal touched 1030 directly.
            </div>
        </div>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-head"><div class="card-title">By bus company</div></div>
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr><th>Company</th><th class="num">Not due</th><th class="num">1–30</th>
                            <th class="num">31–60</th><th class="num">61–90</th><th class="num">90+</th><th class="num">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($report['by_company'] as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('reports.bus-company-statement', $row['company']) }}" class="hover:text-signal">
                                        {{ $row['company']?->name ?? '—' }}
                                    </a>
                                </td>
                                <td class="num"><x-money :value="$row['current']" muted/></td>
                                <td class="num"><x-money :value="$row['1_30']" muted/></td>
                                <td class="num"><x-money :value="$row['31_60']" muted/></td>
                                <td class="num"><x-money :value="$row['61_90']" muted/></td>
                                <td class="num"><x-money :value="$row['over_90']" muted/></td>
                                <td class="num"><x-money :value="$row['total']"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty title="Nothing outstanding"
                                message="Every invoice has been collected."/></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6">Total outstanding</td><td class="num"><x-money :value="$report['total']"/></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head"><div class="card-title">Outstanding invoices</div></div>
            <div class="max-h-[560px] overflow-y-auto">
                <table class="table table-compact">
                    <thead>
                        <tr><th>Invoice</th><th>Company</th><th>Due</th><th class="num">Days</th><th class="num">Outstanding</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($report['invoices'] as $invoice)
                            @php $days = $invoice->due_date->lt($asAt) ? (int) $invoice->due_date->diffInDays($asAt) : 0; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('invoices.show', $invoice) }}" class="money text-xs hover:text-signal">
                                        {{ $invoice->number }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $invoice->busCompany->name }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $invoice->due_date->format('j M Y') }}</td>
                                <td class="num {{ $days > 90 ? 'text-negative' : ($days > 0 ? 'text-caution' : 'text-muted') }}">
                                    {{ $days > 0 ? $days : '—' }}
                                </td>
                                <td class="num"><x-money :value="$invoice->balance_due"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-faint">Nothing outstanding</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
