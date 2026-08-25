@extends('layouts.app')
@section('title', $expense->exists ? 'Edit '.$expense->number : 'Record an expense')

@section('content')
    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}"
          class="mx-auto flex max-w-3xl flex-col gap-4"
          x-data="{
              paymentStatus: '{{ old('payment_status', $expense->payment_status ?? 'paid') }}',
              treatment: '{{ old('treatment', $expense->treatment?->value ?? 'opex') }}',
              amount: {{ old('amount', $expense->amount ?? 0) }},
              tax: {{ old('tax_amount', $expense->tax_amount ?? 0) }},
              get total() { return (parseFloat(this.amount) || 0) + (parseFloat(this.tax) || 0); },
          }">
        @csrf
        @if ($expense->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Expense</div>
                    <div class="card-sub">Nothing reaches the ledger until the entry is posted.</div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="expense_date">Date</label>
                    <input id="expense_date" name="expense_date" type="date" class="input" required
                           value="{{ old('expense_date', $expense->expense_date?->toDateString() ?? now()->toDateString()) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="description">Description</label>
                    <input id="description" name="description" class="input @error('description') input-invalid @enderror"
                           required value="{{ old('description', $expense->description) }}">
                    @error('description')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="label" for="account_id">Account</label>
                    <select id="account_id" name="account_id" class="select @error('account_id') input-invalid @enderror" required>
                        <option value="">Choose an account</option>
                        @foreach ($accounts->groupBy('class') as $class => $group)
                            <optgroup label="{{ $class }}">
                                @foreach ($group as $account)
                                    <option value="{{ $account->id }}" @selected(old('account_id', $expense->account_id) == $account->id)>
                                        {{ $account->code }} — {{ $account->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('account_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="department_id">Cost centre</label>
                    <select id="department_id" name="department_id" class="select @error('department_id') input-invalid @enderror" required>
                        <option value="">Choose</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $expense->department_id) == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('department_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="project_id">Project</label>
                    <select id="project_id" name="project_id" class="select @error('project_id') input-invalid @enderror" required>
                        <option value="">Choose</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $expense->project_id) == $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('project_id')<div class="field-error">{{ $message }}</div>@enderror
                    <div class="hint">Use company overhead when it belongs to no single project.</div>
                </div>
                <div>
                    <label class="label" for="supplier_id">Supplier</label>
                    <select id="supplier_id" name="supplier_id" class="select">
                        <option value="">—</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $expense->supplier_id) == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Classification</div>
                    <div class="card-sub">
                        Scope of work §7 — fixed against variable, and capital against operating, must both
                        be reportable.
                    </div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="nature">Nature</label>
                    <select id="nature" name="nature" class="select">
                        <option value="variable" @selected(old('nature', $expense->nature?->value) === 'variable')>Variable</option>
                        <option value="fixed" @selected(old('nature', $expense->nature?->value) === 'fixed')>Fixed</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="treatment">Treatment</label>
                    <select id="treatment" name="treatment" class="select" x-model="treatment">
                        <option value="opex">Operating expenditure — charged this period</option>
                        <option value="capex">Capital expenditure — capitalised and depreciated</option>
                    </select>
                </div>
                <div class="sm:col-span-2" x-show="treatment === 'capex'" x-cloak>
                    <div class="flex items-start gap-2.5 rounded-md border border-line bg-mist px-3.5 py-2.5 text-xs text-body">
                        <x-icon name="info" class="mt-px size-4 shrink-0 text-signal"/>
                        <div class="leading-relaxed">
                            Choose a fixed asset account (1110–1140) above. Once posted, capitalise the expense
                            from its page to add it to the asset register and start depreciation.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div class="card-title">Amount and payment</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="amount">Amount</label>
                    <input id="amount" name="amount" type="number" step="1" min="0" required
                           class="input input-num @error('amount') input-invalid @enderror"
                           x-model="amount" value="{{ old('amount', $expense->amount) }}">
                    @error('amount')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="label" for="tax_amount">Tax</label>
                    <input id="tax_amount" name="tax_amount" type="number" step="1" min="0" class="input input-num"
                           x-model="tax" value="{{ old('tax_amount', $expense->tax_amount ?? 0) }}">
                </div>
                <div>
                    <label class="label">Total</label>
                    <div class="input input-num bg-mist" x-text="new Intl.NumberFormat().format(Math.round(total))"></div>
                </div>

                <div>
                    <label class="label" for="payment_status">Payment</label>
                    <select id="payment_status" name="payment_status" class="select" x-model="paymentStatus">
                        <option value="paid">Paid now</option>
                        <option value="unpaid">On credit — supplier bill</option>
                    </select>
                </div>
                <div x-show="paymentStatus === 'paid'" x-cloak>
                    <label class="label" for="paid_from_account_id">Paid from</label>
                    <select id="paid_from_account_id" name="paid_from_account_id" class="select">
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account->id }}"
                                @selected(old('paid_from_account_id', $expense->paid_from_account_id ?? $cashAccounts->firstWhere('code', '1020')?->id) == $account->id)>
                                {{ $account->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div x-show="paymentStatus === 'unpaid'" x-cloak>
                    <label class="label" for="due_date">Due date</label>
                    <input id="due_date" name="due_date" type="date" class="input"
                           value="{{ old('due_date', $expense->due_date?->toDateString()) }}">
                    <div class="hint">Credits 2010 accounts payable.</div>
                </div>

                <div>
                    <label class="label" for="reference">Reference</label>
                    <input id="reference" name="reference" class="input" value="{{ old('reference', $expense->reference) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes', $expense->notes) }}</textarea>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-rule px-5 py-4">
                <label class="flex cursor-pointer items-center gap-2 text-xs text-body">
                    <input type="checkbox" name="post_now" value="1" class="size-3.5 accent-navy" checked>
                    Post to the ledger immediately
                </label>
                <div class="flex items-center gap-2">
                    <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">{{ $expense->exists ? 'Save changes' : 'Record expense' }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
