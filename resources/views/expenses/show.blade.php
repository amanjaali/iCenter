@extends('layouts.app')
@section('title', 'Expense '.$expense->number)
@section('subtitle', strtoupper($expense->expense_date->format('j M Y').' · '.$expense->account->code))

@section('actions')
    @can('manage-expenses')
        @if ($expense->isDraft())
            <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-secondary">Edit</a>
            <x-confirm-form :action="route('expenses.post', $expense)" class="btn btn-primary"
                confirm="Posting commits this expense to the ledger. It can then only be corrected by reversal.">
                Post expense
            </x-confirm-form>
        @endif
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="eyebrow">Amount</div>
                        <div class="mt-1.5 font-display text-3xl font-medium">
                            {{ \App\Support\Money::format($expense->total) }}
                            <span class="text-sm font-normal text-faint">{{ config('allva.currency.code') }}</span>
                        </div>
                        @if ((float) $expense->tax_amount > 0)
                            <div class="mt-1 text-xs text-muted">
                                {{ \App\Support\Money::format($expense->amount) }} plus
                                {{ \App\Support\Money::format($expense->tax_amount) }} tax
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <x-badge :status="$expense->status"
                                 :tone="$expense->status === 'posted' ? 'success' : ($expense->status === 'void' ? 'danger' : 'neutral')"/>
                        <x-badge :label="$expense->nature->label()" tone="neutral"/>
                        <x-badge :label="$expense->treatment->label()" :tone="$expense->isCapex() ? 'info' : 'neutral'"/>
                        <x-badge :label="$expense->payment_status === 'paid' ? 'Paid' : 'Unpaid'"
                                 :tone="$expense->payment_status === 'paid' ? 'success' : 'warning'"/>
                    </div>
                </div>
                <p class="mt-4 border-t border-rule pt-4 text-xs leading-relaxed text-body">{{ $expense->description }}</p>
            </div>

            @if ($expense->journal)
                <div class="card">
                    <div class="card-head">
                        <div>
                            <div class="card-title">Ledger posting</div>
                            <div class="card-sub">{{ $expense->journal->journal_date->format('j M Y') }}</div>
                        </div>
                        <a href="{{ route('journals.show', $expense->journal) }}" class="money text-xs text-muted hover:text-signal">
                            {{ $expense->journal->reference }}
                        </a>
                    </div>
                    <table class="table table-compact">
                        <thead><tr><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                        <tbody>
                            @foreach ($expense->journal->lines as $line)
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

            @if ($expense->payments->isNotEmpty())
                <div class="card">
                    <div class="card-head"><div class="card-title">Supplier payments</div></div>
                    <table class="table table-compact">
                        <thead><tr><th>Reference</th><th>Date</th><th>From</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($expense->payments as $payment)
                                <tr>
                                    <td class="money text-xs">{{ $payment->reference }}</td>
                                    <td class="text-muted">{{ $payment->payment_date->format('j M Y') }}</td>
                                    <td class="text-muted">{{ $payment->sourceAccount->displayName() }}</td>
                                    <td class="num"><x-money :value="$payment->amount"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @can('manage-expenses')
                @if ($expense->isPosted() && $expense->payment_status === 'unpaid' && $expense->balanceDue() > 0)
                    <form method="POST" action="{{ route('expenses.pay', $expense) }}" class="card">
                        @csrf
                        <div class="card-head">
                            <div>
                                <div class="card-title">Settle this bill</div>
                                <div class="card-sub">
                                    {{ \App\Support\Money::format($expense->balanceDue(), true) }} outstanding in accounts payable.
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 p-5 sm:grid-cols-4">
                            <div>
                                <label class="label" for="payment_date">Date</label>
                                <input id="payment_date" name="payment_date" type="date" class="input" required value="{{ now()->toDateString() }}">
                            </div>
                            <div>
                                <label class="label" for="pay_amount">Amount</label>
                                <input id="pay_amount" name="amount" type="number" step="1" min="1"
                                       max="{{ $expense->balanceDue() }}" class="input input-num" required
                                       value="{{ $expense->balanceDue() }}">
                            </div>
                            <div>
                                <label class="label" for="source_account_id">Paid from</label>
                                <select id="source_account_id" name="source_account_id" class="select" required>
                                    @foreach ($cashAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="method">Method</label>
                                <select id="method" name="method" class="select">
                                    @foreach (['bank' => 'Bank', 'cash' => 'Cash', 'cheque' => 'Cheque', 'transfer' => 'Transfer'] as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-rule px-5 py-4">
                            <button class="btn btn-primary btn-sm">Record payment</button>
                        </div>
                    </form>
                @endif

                @if ($expense->isCapex() && $expense->isPosted() && $expense->fixedAsset->isEmpty())
                    <form method="POST" action="{{ route('expenses.capitalise', $expense) }}" class="card">
                        @csrf
                        <div class="card-head">
                            <div>
                                <div class="card-title">Capitalise into the asset register</div>
                                <div class="card-sub">
                                    Scope of work §7 — capital expenditure is depreciated rather than charged
                                    to the period. This creates the register entry that drives it.
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 p-5 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="label" for="asset_name">Asset name</label>
                                <input id="asset_name" name="name" class="input" required value="{{ $expense->description }}">
                            </div>
                            <div>
                                <label class="label" for="code">Asset code</label>
                                <input id="code" name="code" class="input money" placeholder="Auto">
                            </div>
                            <div>
                                <label class="label" for="category">Category</label>
                                <select id="category" name="category" class="select" required>
                                    @foreach (['it' => 'IT equipment', 'vehicle' => 'Vehicle', 'furniture' => 'Furniture', 'intangible' => 'Software / intangible'] as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="useful_life_months">Useful life (months)</label>
                                <input id="useful_life_months" name="useful_life_months" type="number" min="1" max="600"
                                       class="input input-num" required value="36">
                            </div>
                            <div>
                                <label class="label" for="salvage_value">Salvage value</label>
                                <input id="salvage_value" name="salvage_value" type="number" step="1" min="0"
                                       class="input input-num" value="0">
                            </div>
                            <div>
                                <label class="label" for="depreciation_start_date">Depreciation starts</label>
                                <input id="depreciation_start_date" name="depreciation_start_date" type="date"
                                       class="input" value="{{ $expense->expense_date->toDateString() }}">
                            </div>
                            <div>
                                <label class="label" for="depreciation_expense_account_id">Charge to</label>
                                <select id="depreciation_expense_account_id" name="depreciation_expense_account_id" class="select">
                                    @foreach ($depreciationAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="serial_number">Serial number</label>
                                <input id="serial_number" name="serial_number" class="input money">
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-rule px-5 py-4">
                            <button class="btn btn-primary btn-sm">Capitalise asset</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-3">Details</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Number' => $expense->number,
                        'Account' => $expense->account->displayName(),
                        'Cost centre' => $expense->department->name,
                        'Project' => $expense->project->name,
                        'Supplier' => $expense->supplier?->name,
                        'Paid from' => $expense->paidFromAccount?->displayName(),
                        'Due date' => $expense->due_date?->format('j M Y'),
                        'Reference' => $expense->reference,
                        'Period' => $expense->period?->code,
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-faint">{{ $label }}</dt>
                            <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if ($expense->fixedAsset->isNotEmpty())
                <div class="card card-pad">
                    <div class="eyebrow mb-3">Capitalised as</div>
                    @foreach ($expense->fixedAsset as $asset)
                        <a href="{{ route('assets.show', $asset) }}" class="btn btn-secondary btn-sm w-full justify-between">
                            <span>{{ $asset->name }}</span>
                            <span class="money">{{ $asset->code }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @can('manage-expenses')
                <div class="card card-pad">
                    <div class="eyebrow mb-3">Actions</div>
                    <div class="flex flex-col gap-2">
                        @if ($expense->isDraft())
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                  data-confirm="Delete this draft expense? It has not reached the ledger.">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm w-full justify-center">Delete draft</button>
                            </form>
                        @elseif ($expense->status !== 'void')
                            <x-confirm-form :action="route('expenses.void', $expense)" reason
                                reason-label="Why is this expense being voided?"
                                confirm="Voiding reverses the journal rather than deleting it — both entries stay on the record (§9.3)."
                                class="btn btn-danger btn-sm w-full justify-center">
                                Void expense
                            </x-confirm-form>
                        @endif
                    </div>
                </div>
            @endcan

            @if ($expense->notes)
                <div class="card card-pad">
                    <div class="eyebrow mb-2">Notes</div>
                    <p class="whitespace-pre-line text-xs leading-relaxed text-muted">{{ $expense->notes }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
