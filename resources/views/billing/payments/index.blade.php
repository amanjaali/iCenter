@extends('layouts.app')
@section('title', 'Receipts')
@section('subtitle', number_format($totals->count ?? 0).' RECEIPTS · '.\App\Support\Money::format($totals->received ?? 0).' RECEIVED')

@section('actions')
    @can('manage-payments')
        <a href="{{ route('payments.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Record receipt
        </a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[180px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Receipt or bank reference">
        </div>
        <div class="min-w-[180px]">
            <label class="label" for="bus_company_id">Bus company</label>
            <select id="bus_company_id" name="bus_company_id" class="select">
                <option value="">All companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('bus_company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="from">From</label>
            <input id="from" name="from" type="date" class="input" value="{{ request('from') }}">
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="to">To</label>
            <input id="to" name="to" type="date" class="input" value="{{ request('to') }}">
        </div>
        <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Filter</button>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    @if (($totals->unallocated ?? 0) > 0)
        <div class="mb-4 flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
            <x-icon name="info" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                {{ \App\Support\Money::format($totals->unallocated, true) }} of receipts is unallocated —
                cash received ahead of the invoices it will settle. Apply it from each receipt as those months are billed.
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Bus company</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Deposited to</th>
                        <th>Applied to</th>
                        <th class="num">Amount</th>
                        <th class="num">Unallocated</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>
                                <a href="{{ route('payments.show', $payment) }}" class="money text-xs hover:text-signal">
                                    {{ $payment->number }}
                                </a>
                                @if ($payment->reference)<div class="eyebrow">{{ $payment->reference }}</div>@endif
                            </td>
                            <td>{{ $payment->busCompany->name }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $payment->payment_date->format('j M Y') }}</td>
                            <td class="text-muted">{{ ucfirst($payment->method) }}</td>
                            <td class="text-muted"><span class="money">{{ $payment->depositAccount->code }}</span></td>
                            <td class="text-muted">
                                @forelse ($payment->allocations->take(2) as $allocation)
                                    <a href="{{ route('invoices.show', $allocation->invoice_id) }}"
                                       class="money text-[11px] hover:text-signal">{{ $allocation->invoice?->number }}</a>@if(! $loop->last), @endif
                                @empty
                                    <span class="text-faint">—</span>
                                @endforelse
                                @if ($payment->allocations->count() > 2)
                                    <span class="text-[11px] text-faint">+{{ $payment->allocations->count() - 2 }}</span>
                                @endif
                            </td>
                            <td class="num"><x-money :value="$payment->amount"/></td>
                            <td class="num"><x-money :value="$payment->unallocated_amount" muted/></td>
                            <td>
                                <x-badge :status="$payment->status"
                                         :tone="$payment->status === 'posted' ? 'success' : ($payment->status === 'void' ? 'danger' : 'neutral')"/>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty title="No receipts recorded"
                                         message="Scope of work §4.3: the system tracks invoiced, collected and outstanding amounts per bus company, per month.">
                                    @can('manage-payments')
                                        <a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm">Record the first receipt</a>
                                    @endcan
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())<div class="border-t border-rule px-5 py-3">{{ $payments->links() }}</div>@endif
    </div>
@endsection
