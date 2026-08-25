@extends('layouts.app')
@section('title', 'Receipt '.$payment->number)
@section('subtitle', strtoupper($payment->busCompany->name.' · '.$payment->payment_date->format('j M Y')))

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Applied to</div>
                        <div class="card-sub">Which invoices this receipt settles.</div>
                    </div>
                    <x-badge :status="$payment->status"
                             :tone="$payment->status === 'posted' ? 'success' : ($payment->status === 'void' ? 'danger' : 'neutral')"/>
                </div>
                <table class="table">
                    <thead>
                        <tr><th>Invoice</th><th>Month</th><th class="num">Invoice total</th><th class="num">Applied</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->allocations as $allocation)
                            <tr>
                                <td>
                                    <a href="{{ route('invoices.show', $allocation->invoice) }}" class="money text-xs hover:text-signal">
                                        {{ $allocation->invoice->number }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $allocation->invoice->billing_month->format('M Y') }}</td>
                                <td class="num"><x-money :value="$allocation->invoice->total"/></td>
                                <td class="num"><x-money :value="$allocation->amount"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty title="Not yet applied"
                                message="This receipt is being held as advance cash. Apply it to invoices below as those months are billed."/></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3">Received</td>
                            <td class="num"><x-money :value="$payment->amount"/></td>
                        </tr>
                        <tr>
                            <td colspan="3">Unallocated</td>
                            <td class="num"><x-money :value="$payment->unallocated_amount" muted/></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @can('manage-payments')
                @if ((float) $payment->unallocated_amount > 0 && $payment->isPosted() && $outstanding->isNotEmpty())
                    <form method="POST" action="{{ route('payments.allocate', $payment) }}" class="card">
                        @csrf
                        <div class="card-head">
                            <div>
                                <div class="card-title">Apply the remaining {{ \App\Support\Money::format($payment->unallocated_amount, true) }}</div>
                                <div class="card-sub">Advance cash released against invoices as they are raised (§4.4).</div>
                            </div>
                        </div>
                        <table class="table">
                            <thead>
                                <tr><th>Invoice</th><th>Month</th><th class="num">Outstanding</th><th class="num w-[150px]">Apply</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($outstanding as $index => $invoice)
                                    <tr>
                                        <td>
                                            <span class="money text-xs">{{ $invoice->number }}</span>
                                            <input type="hidden" name="allocations[{{ $index }}][invoice_id]" value="{{ $invoice->id }}">
                                        </td>
                                        <td class="text-muted">{{ $invoice->billing_month->format('M Y') }}</td>
                                        <td class="num"><x-money :value="$invoice->balance_due"/></td>
                                        <td class="num">
                                            <input type="number" step="1" min="0" max="{{ (float) $invoice->balance_due }}"
                                                   name="allocations[{{ $index }}][amount]" class="input input-num">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="flex justify-end border-t border-rule px-5 py-4">
                            <button class="btn btn-primary btn-sm">Apply receipt</button>
                        </div>
                    </form>
                @endif
            @endcan

            @if ($payment->journal)
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">Ledger posting</div>
                            <div class="card-sub">{{ $payment->journal->journal_date->format('j M Y') }}</div>
                        </div>
                        <a href="{{ route('journals.show', $payment->journal) }}" class="money text-xs text-muted hover:text-signal">
                            {{ $payment->journal->reference }}
                        </a>
                    </div>
                    <table class="table table-compact">
                        <thead><tr><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                        <tbody>
                            @foreach ($payment->journal->lines as $line)
                                <tr>
                                    <td><span class="money">{{ $line->account->code }}</span> {{ $line->account->name }}</td>
                                    <td class="text-muted">{{ $line->description }}</td>
                                    <td class="num">{{ (float) $line->debit > 0 ? \App\Support\Money::format($line->debit) : '' }}</td>
                                    <td class="num">{{ (float) $line->credit > 0 ? \App\Support\Money::format($line->credit) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-3">Receipt</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Number' => $payment->number,
                        'Bus company' => $payment->busCompany->name,
                        'Received' => $payment->payment_date->format('j M Y'),
                        'Method' => ucfirst($payment->method),
                        'Deposited to' => $payment->depositAccount->displayName(),
                        'Bank reference' => $payment->reference,
                        'Period' => $payment->period?->code,
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @can('manage-payments')
                @if ($payment->status !== 'void')
                    <div class="card card-pad">
                        <div class="eyebrow mb-3">Actions</div>
                        <x-confirm-form :action="route('payments.void', $payment)" reason
                            reason-label="Why is this receipt being voided?"
                            confirm="Voiding reverses the journal and unapplies the receipt from every invoice. The original entry stays on the record (§9.3)."
                            class="btn btn-danger btn-sm w-full justify-center">
                            Void receipt
                        </x-confirm-form>
                    </div>
                @endif
            @endcan

            @if ($payment->notes)
                <div class="card card-pad">
                    <div class="eyebrow mb-2">Notes</div>
                    <p class="whitespace-pre-line text-xs leading-relaxed text-muted">{{ $payment->notes }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
