@extends('layouts.app')
@section('title', 'Payroll '.$run->payroll_month->format('F Y'))
@section('subtitle', $run->reference.' · '.strtoupper($run->status))

@section('actions')
    @can('manage-payroll')
        @if ($run->isDraft())
            <x-confirm-form :action="route('payroll.approve', $run)" class="btn btn-secondary"
                confirm="Approving locks the lines against further editing, ready for posting.">
                Approve
            </x-confirm-form>
        @endif
        @if (in_array($run->status, ['draft', 'approved'], true))
            <x-confirm-form :action="route('payroll.post', $run)" class="btn btn-primary"
                confirm="Posting charges salaries to accounts 6010–6090, credits net pay to accrued salaries (2020) and the withholdings to payroll taxes payable (2030).">
                Post to ledger
            </x-confirm-form>
        @endif
    @endcan
@endsection

@section('content')
    <div class="mb-4 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Gross pay" :value="\App\Support\Money::format($run->gross_total)" accent="#0A1F3D"
               :delta="$run->lines->count().' employees'"/>
        <x-kpi label="Deductions" :value="\App\Support\Money::format($run->deductions_total)" accent="#98A2B3"
               delta="Withheld from gross"/>
        <x-kpi label="Net pay" :value="\App\Support\Money::format($run->net_total)" accent="#00C2E8"
               delta="Owed to employees"/>
        <x-kpi label="Total company cost" :value="\App\Support\Money::format($run->totalCost())" accent="#0E7C5A"
               delta="Gross plus employer social security"/>
    </div>

    <div class="card mb-4 overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Payroll lines</div>
                <div class="card-sub">
                    {{ $run->isDraft() ? 'Editable — change a figure and save the line.' : 'Locked; this run has been approved or posted.' }}
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Employee</th><th>Cost centre</th><th>Account</th>
                        <th class="num">Base</th><th class="num">Overtime</th><th class="num">Bonus</th>
                        <th class="num">Allowances</th><th class="num">Gross</th>
                        <th class="num">Deductions</th><th class="num">Net</th><th class="num">Employer SS</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($run->lines as $line)
                        @if ($run->isDraft())
                            <tr>
                                <form method="POST" action="{{ route('payroll.update-line', $line) }}" id="line-{{ $line->id }}">
                                    @csrf @method('PATCH')
                                </form>
                                <td>
                                    <span class="font-medium">{{ $line->employee->name }}</span>
                                    <div class="eyebrow">{{ $line->employee->code }}</div>
                                </td>
                                <td class="text-muted">{{ $line->department->name }}</td>
                                <td class="text-muted"><span class="money">{{ $line->salaryAccount->code }}</span></td>
                                @foreach (['base_salary', 'overtime', 'bonus', 'allowances'] as $field)
                                    <td class="num">
                                        <input form="line-{{ $line->id }}" name="{{ $field }}" type="number" step="1" min="0"
                                               class="input input-num w-[100px]" value="{{ (float) $line->{$field} }}">
                                    </td>
                                @endforeach
                                <td class="num"><x-money :value="$line->gross"/></td>
                                <td class="num">
                                    <input form="line-{{ $line->id }}" name="other_deductions" type="number" step="1" min="0"
                                           class="input input-num w-[100px]" value="{{ (float) $line->other_deductions }}"
                                           title="Other deductions; social security and tax are calculated">
                                </td>
                                <td class="num"><x-money :value="$line->net"/></td>
                                <td class="num"><x-money :value="$line->employer_ss" muted/></td>
                                <td class="text-end">
                                    <button form="line-{{ $line->id }}" class="btn btn-ghost btn-sm" aria-label="Save line">
                                        <x-icon name="check" class="size-3.5"/>
                                    </button>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td>
                                    <span class="font-medium">{{ $line->employee->name }}</span>
                                    <div class="eyebrow">{{ $line->employee->code }}</div>
                                </td>
                                <td class="text-muted">{{ $line->department->name }}</td>
                                <td class="text-muted"><span class="money">{{ $line->salaryAccount->code }}</span></td>
                                <td class="num"><x-money :value="$line->base_salary"/></td>
                                <td class="num"><x-money :value="$line->overtime" muted/></td>
                                <td class="num"><x-money :value="$line->bonus" muted/></td>
                                <td class="num"><x-money :value="$line->allowances" muted/></td>
                                <td class="num"><x-money :value="$line->gross"/></td>
                                <td class="num"><x-money :value="$line->totalDeductions()" muted/></td>
                                <td class="num"><x-money :value="$line->net"/></td>
                                <td class="num"><x-money :value="$line->employer_ss" muted/></td>
                                <td class="text-end">
                                    <a href="{{ route('payroll.payslip', $line) }}" target="_blank" class="btn btn-ghost btn-sm"
                                       aria-label="Payslip">
                                        <x-icon name="print" class="size-3.5"/>
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7">Totals</td>
                        <td class="num"><x-money :value="$run->gross_total"/></td>
                        <td class="num"><x-money :value="$run->deductions_total"/></td>
                        <td class="num"><x-money :value="$run->net_total"/></td>
                        <td class="num"><x-money :value="$run->employer_ss_total"/></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        @if ($run->journal)
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Ledger posting</div>
                        <div class="card-sub">{{ $run->journal->journal_date->format('j M Y') }}</div>
                    </div>
                    <a href="{{ route('journals.show', $run->journal) }}" class="money text-xs text-muted hover:text-signal">
                        {{ $run->journal->reference }}
                    </a>
                </div>
                <table class="table table-compact">
                    <thead><tr><th>Account</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                    <tbody>
                        @foreach ($run->journal->lines as $line)
                            <tr>
                                <td>
                                    <span class="money">{{ $line->account->code }}</span> {{ $line->account->name }}
                                    @if ($line->department)<div class="eyebrow">{{ $line->department->name }}</div>@endif
                                </td>
                                <td class="num">{{ (float) $line->debit > 0 ? \App\Support\Money::format($line->debit) : '' }}</td>
                                <td class="num">{{ (float) $line->credit > 0 ? \App\Support\Money::format($line->credit) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @can('manage-payroll')
            @if ($run->isPosted() && $run->outstandingAmount() > 0)
                <form method="POST" action="{{ route('payroll.pay', $run) }}" class="card self-start">
                    @csrf
                    <div class="card-head">
                        <div>
                            <div class="card-title">Pay salaries</div>
                            <div class="card-sub">
                                {{ \App\Support\Money::format($run->outstandingAmount(), true) }} still accrued in 2020.
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <label class="label" for="payment_date">Payment date</label>
                            <input id="payment_date" name="payment_date" type="date" class="input" required value="{{ now()->toDateString() }}">
                        </div>
                        <div>
                            <label class="label" for="amount">Amount</label>
                            <input id="amount" name="amount" type="number" step="1" min="1"
                                   max="{{ $run->outstandingAmount() }}" class="input input-num" required
                                   value="{{ $run->outstandingAmount() }}">
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
                                <option value="bank">Bank transfer</option>
                                <option value="cash">Cash</option>
                                <option value="transfer">Other transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-rule px-5 py-4">
                        <button class="btn btn-primary btn-sm">Record salary payment</button>
                    </div>
                </form>
            @endif
        @endcan

        @if ($run->payments->isNotEmpty())
            <div class="card self-start">
                <div class="card-head"><div class="card-title">Salary payments</div></div>
                <table class="table table-compact">
                    <thead><tr><th>Reference</th><th>Date</th><th>From</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($run->payments as $payment)
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
    </div>
@endsection
