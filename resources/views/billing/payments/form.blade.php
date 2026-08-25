@extends('layouts.app')
@section('title', 'Record a receipt')

@section('content')
    <form method="POST" action="{{ route('payments.store') }}" class="mx-auto flex max-w-4xl flex-col gap-4"
          x-data="{
              amount: {{ old('amount', $preselectedInvoice?->balance_due ?? 0) }},
              allocations: {},
              get allocated() {
                  return Object.values(this.allocations).reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
              },
              get unallocated() { return (parseFloat(this.amount) || 0) - this.allocated; },
              settleAll(outstanding) {
                  // Apply the receipt oldest first, exactly as the service would.
                  let left = parseFloat(this.amount) || 0;
                  for (const row of outstanding) {
                      const apply = Math.min(left, row.balance);
                      this.allocations[row.id] = apply > 0 ? apply : '';
                      left -= apply > 0 ? apply : 0;
                  }
              }
          }">
        @csrf

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Receipt</div>
                    <div class="card-sub">Debits the cash or bank account and credits receivables.</div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="lg:col-span-1">
                    <label class="label" for="bus_company_id">Bus company</label>
                    <select id="bus_company_id" name="bus_company_id" class="select" required
                            onchange="window.location = '{{ route('payments.create') }}?bus_company_id=' + this.value">
                        <option value="">Choose a company</option>
                        @foreach ($companies as $option)
                            <option value="{{ $option->id }}" @selected(($company?->id) == $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="payment_date">Received on</label>
                    <input id="payment_date" name="payment_date" type="date" class="input" required
                           value="{{ old('payment_date', now()->toDateString()) }}">
                </div>
                <div>
                    <label class="label" for="amount">Amount ({{ config('allva.currency.code') }})</label>
                    <input id="amount" name="amount" type="number" step="1" min="1" class="input input-num" required
                           x-model="amount" value="{{ old('amount', $preselectedInvoice?->balance_due) }}">
                </div>
                <div>
                    <label class="label" for="method">Method</label>
                    <select id="method" name="method" class="select">
                        @foreach (['bank' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'transfer' => 'Other transfer'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('method') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="deposit_account_id">Deposited to</label>
                    <select id="deposit_account_id" name="deposit_account_id" class="select" required>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('deposit_account_id', $accounts->firstWhere('code', '1020')?->id) == $account->id)>
                                {{ $account->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="reference">Bank reference</label>
                    <input id="reference" name="reference" class="input" value="{{ old('reference') }}">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        @if ($company)
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Apply to invoices</div>
                        <div class="card-sub">
                            Anything left unapplied stays on the receipt as advance cash and can be
                            applied to later invoices as they are raised.
                        </div>
                    </div>
                    @if ($outstanding->isNotEmpty())
                        <button type="button" class="btn btn-secondary btn-sm"
                                @click="settleAll({{ $outstanding->map(fn($i) => ['id' => $i->id, 'balance' => (float) $i->balance_due])->toJson() }})">
                            Apply oldest first
                        </button>
                    @endif
                </div>

                @if ($outstanding->isEmpty())
                    <x-empty title="Nothing outstanding"
                             message="This company has no unpaid invoices. The receipt will be held as advance cash."/>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Month</th>
                                    <th>Due</th>
                                    <th class="num">Total</th>
                                    <th class="num">Outstanding</th>
                                    <th class="num w-[150px]">Apply</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($outstanding as $index => $invoice)
                                    <tr>
                                        <td>
                                            <span class="money text-xs">{{ $invoice->number }}</span>
                                            <input type="hidden" name="allocations[{{ $index }}][invoice_id]" value="{{ $invoice->id }}">
                                        </td>
                                        <td class="whitespace-nowrap text-muted">{{ $invoice->billing_month->format('M Y') }}</td>
                                        <td class="whitespace-nowrap {{ $invoice->isOverdue() ? 'text-negative' : 'text-muted' }}">
                                            {{ $invoice->due_date->format('j M Y') }}
                                        </td>
                                        <td class="num"><x-money :value="$invoice->total"/></td>
                                        <td class="num"><x-money :value="$invoice->balance_due"/></td>
                                        <td class="num">
                                            <input type="number" step="1" min="0" max="{{ (float) $invoice->balance_due }}"
                                                   name="allocations[{{ $index }}][amount]" class="input input-num"
                                                   x-model="allocations[{{ $invoice->id }}]"
                                                   @if ($preselectedInvoice?->id === $invoice->id)
                                                       value="{{ (float) $invoice->balance_due }}"
                                                   @endif>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4">Applied</td>
                                    <td class="num" x-text="new Intl.NumberFormat().format(Math.round(allocated))"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        Unallocated
                                        <span class="ms-2 text-[11px] font-normal text-faint"
                                              x-show="unallocated < -0.5">More has been applied than was received.</span>
                                    </td>
                                    <td class="num" :class="unallocated < -0.5 && 'text-negative'"
                                        x-text="new Intl.NumberFormat().format(Math.round(unallocated))"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ route('payments.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" @disabled(! $company)>Record and post receipt</button>
        </div>
    </form>
@endsection
