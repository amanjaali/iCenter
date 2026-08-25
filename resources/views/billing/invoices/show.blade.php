@extends('layouts.app')
@section('title', 'Invoice '.$invoice->number)
@section('subtitle', strtoupper($invoice->busCompany->name.' · '.$invoice->billing_month->format('F Y')))

@section('actions')
    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="btn btn-secondary">
        <x-icon name="print" class="size-3.5"/> Print
    </a>
    @can('manage-invoices')
        @if ($invoice->isDraft())
            <x-confirm-form :action="route('invoices.issue', $invoice)" class="btn btn-primary"
                confirm="Issuing posts this invoice to the ledger: receivables are debited and revenue credited. It can then only be corrected by reversal.">
                Issue invoice
            </x-confirm-form>
        @elseif (! $invoice->isVoid() && (float) $invoice->balance_due > 0)
            <a href="{{ route('payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-primary">
                <x-icon name="wallet" class="size-3.5"/> Record payment
            </a>
        @endif
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Invoice lines</div>
                        <div class="card-sub">
                            One line per bus. {{ $invoice->proration_method->label() }} —
                            the rule that was in force when this invoice was raised.
                        </div>
                    </div>
                    <x-badge :status="$invoice->status"/>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Bus</th>
                                <th class="num">Students</th>
                                <th class="num">Billable months</th>
                                <th class="num">Rate</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->lines as $line)
                                <tr x-data="{ open: false }">
                                    <td>
                                        <button type="button" @click="open = !open"
                                                class="flex items-center gap-1.5 text-start hover:text-signal">
                                            <x-icon name="chevron-right" class="size-3 transition-transform"
                                                    ::class="open && 'rotate-90'"/>
                                            <span>{{ $line->bus?->label() ?? 'Unassigned students' }}</span>
                                        </button>
                                    </td>
                                    <td class="num">{{ number_format($line->student_count) }}</td>
                                    <td class="num">{{ number_format($line->billable_units, 4) }}</td>
                                    <td class="num">{{ \App\Support\Money::format($line->unit_price) }}</td>
                                    <td class="num"><x-money :value="$line->line_total"/></td>
                                </tr>
                                {{-- Per-student detail: §4.3 requires that nothing be lost or untracked. --}}
                                <template x-if="open">
                                    <tr>
                                        <td colspan="5" class="!p-0">
                                            <table class="table table-compact bg-mist/50">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th class="num">Days</th>
                                                        <th class="num">Units</th>
                                                        <th>Note</th>
                                                        <th class="num">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($line->students as $detail)
                                                        <tr>
                                                            <td>
                                                                <a href="{{ route('students.show', $detail->student_id) }}"
                                                                   class="hover:text-signal">{{ $detail->student?->name ?? '—' }}</a>
                                                                <span class="eyebrow ms-1">{{ $detail->student?->code }}</span>
                                                            </td>
                                                            <td class="num">{{ $detail->billable_days }} / {{ $detail->days_in_month }}</td>
                                                            <td class="num">{{ number_format($detail->billable_units, 4) }}</td>
                                                            <td class="text-muted">{{ $detail->note ?: ($detail->is_partial_month ? 'Part month' : '') }}</td>
                                                            <td class="num"><x-money :value="$detail->amount"/></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </template>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if ((float) $invoice->discount_amount > 0)
                                <tr>
                                    <td colspan="4">Subtotal</td>
                                    <td class="num"><x-money :value="$invoice->subtotal"/></td>
                                </tr>
                                <tr>
                                    <td colspan="4">Discount</td>
                                    <td class="num"><x-money :value="-$invoice->discount_amount"/></td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="4">Total</td>
                                <td class="num"><x-money :value="$invoice->total"/></td>
                            </tr>
                            @if ((float) $invoice->amount_paid > 0)
                                <tr>
                                    <td colspan="4">Collected</td>
                                    <td class="num"><x-money :value="-$invoice->amount_paid"/></td>
                                </tr>
                                <tr>
                                    <td colspan="4">Outstanding</td>
                                    <td class="num"><x-money :value="$invoice->balance_due"/></td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($invoice->allocations->isNotEmpty())
                <div class="card">
                    <div class="card-head"><div class="card-title">Payments applied</div></div>
                    <table class="table table-compact">
                        <thead>
                            <tr><th>Receipt</th><th>Date</th><th>Method</th><th class="num">Applied</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->allocations as $allocation)
                                <tr>
                                    <td>
                                        <a href="{{ route('payments.show', $allocation->payment) }}" class="money text-xs hover:text-signal">
                                            {{ $allocation->payment->number }}
                                        </a>
                                    </td>
                                    <td class="text-muted">{{ $allocation->payment->payment_date->format('j M Y') }}</td>
                                    <td class="text-muted">{{ ucfirst($allocation->payment->method) }}</td>
                                    <td class="num"><x-money :value="$allocation->amount"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @foreach ([$invoice->journal, $invoice->recognitionJournal] as $journal)
                @if ($journal)
                    <div class="card">
                        <div class="card-head">
                            <div>
                                <div class="card-title">
                                    {{ $journal->source_type === \App\Enums\JournalSource::RevenueRecognition
                                        ? 'Revenue recognition journal' : 'Ledger posting' }}
                                </div>
                                <div class="card-sub">{{ $journal->journal_date->format('j M Y') }} · {{ $journal->memo }}</div>
                            </div>
                            <a href="{{ route('journals.show', $journal) }}" class="money text-xs text-muted hover:text-signal">
                                {{ $journal->reference }}
                            </a>
                        </div>
                        <table class="table table-compact">
                            <thead>
                                <tr><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($journal->lines as $line)
                                    <tr>
                                        <td>
                                            <a href="{{ route('accounts.show', $line->account) }}" class="hover:text-signal">
                                                <span class="money">{{ $line->account->code }}</span> {{ $line->account->name }}
                                            </a>
                                        </td>
                                        <td class="text-muted">{{ $line->description }}</td>
                                        <td class="num">{{ (float) $line->debit > 0 ? \App\Support\Money::format($line->debit) : '' }}</td>
                                        <td class="num">{{ (float) $line->credit > 0 ? \App\Support\Money::format($line->credit) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-3">Invoice</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Number' => $invoice->number,
                        'Bus company' => $invoice->busCompany->name,
                        'Billing month' => $invoice->billing_month->format('F Y'),
                        'Academic year' => $invoice->academicYear?->name,
                        'Issued' => $invoice->issue_date->format('j M Y'),
                        'Due' => $invoice->due_date->format('j M Y'),
                        'Period' => $invoice->period?->code,
                        'Students' => number_format($invoice->student_count),
                        'Mid-month rule' => $invoice->proration_method->label(),
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if ($invoice->is_deferred)
                <div class="rounded-md border border-caution/25 bg-caution-soft px-4 py-3">
                    <div class="flex items-start gap-2.5 text-xs text-caution">
                        <x-icon name="info" class="mt-px size-4 shrink-0"/>
                        <div class="leading-relaxed">
                            @if ($invoice->revenue_recognised)
                                Billed in advance and released from deferred revenue on
                                {{ $invoice->recognised_on?->format('j M Y') }}.
                            @else
                                Billed in advance. The credit sits in 2060 deferred revenue and is released to
                                4010 when {{ $invoice->billing_month->format('F Y') }} arrives (scope of work §4.4).
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @can('manage-invoices')
                <div class="card card-pad">
                    <div class="eyebrow mb-3">Actions</div>
                    <div class="flex flex-col gap-2">
                        @if ($invoice->isDraft())
                            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
                                  data-confirm="Delete this draft invoice? It has not reached the ledger.">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm w-full justify-center">Delete draft</button>
                            </form>
                        @elseif (! $invoice->isVoid())
                            <x-confirm-form :action="route('invoices.void', $invoice)" reason
                                reason-label="Why is this invoice being voided?"
                                confirm="Voiding reverses the journal rather than deleting it — the original entry and its reversal both stay on the record (§9.3)."
                                class="btn btn-secondary btn-sm w-full justify-center">
                                Void invoice
                            </x-confirm-form>

                            @if ((float) $invoice->balance_due > 0)
                                <x-confirm-form :action="route('invoices.write-off', $invoice)" reason
                                    reason-label="Why is this being written off?"
                                    confirm="Writing off charges the outstanding balance to 9080 bad debt and clears the receivable."
                                    class="btn btn-danger btn-sm w-full justify-center">
                                    Write off balance
                                </x-confirm-form>
                            @endif
                        @endif
                    </div>
                </div>
            @endcan

            @if ($invoice->notes)
                <div class="card card-pad">
                    <div class="eyebrow mb-2">Notes</div>
                    <p class="whitespace-pre-line text-xs leading-relaxed text-muted">{{ $invoice->notes }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
