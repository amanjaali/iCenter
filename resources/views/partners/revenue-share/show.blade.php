@extends('layouts.app')
@section('title', $run->partner_name.' — '.$run->period->label())
@section('subtitle', $run->reference.' · '.strtoupper($run->status))

@section('actions')
    @can('manage-revenue-share')
        @if ($run->isDraft())
            <x-confirm-form :action="route('revenue-share.post', $run)" class="btn btn-primary"
                :confirm="'Posting charges '.$run->partner_name.'’s share to 5010 as a direct cost and credits 2040 as a payable. The basis in force is frozen onto this run.'">
                Post the share
            </x-confirm-form>
        @endif
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-4">How this was calculated</div>
                <x-stat-row label="Gross revenue for the period" :value="$run->gross_revenue"/>
                @if ($run->basis === \App\Enums\RevenueShareBasis::Net)
                    <x-stat-row label="Operating expenses deducted first" :value="-$run->operating_expenses" indent/>
                @endif
                <x-stat-row :label="$run->basis === \App\Enums\RevenueShareBasis::Gross
                        ? 'Base — gross revenue' : 'Base — net profit'"
                    :value="$run->revenue_base" emphasis/>
                <x-stat-row :label="$run->partner_name.' at '.rtrim(rtrim(number_format($run->share_percent, 2), '0'), '.').'%'"
                    :value="-$run->share_amount" indent/>
                <x-stat-row label="Retained by ALLVA"
                    :value="(float) $run->revenue_base - (float) $run->share_amount" emphasis/>

                @if ($run->basis === \App\Enums\RevenueShareBasis::Gross)
                    <p class="mt-4 border-t border-rule pt-4 text-[11px] leading-relaxed text-faint">
                        On this basis ALLVA's operating expenses come out of its own half. The alternative
                        treatment — expenses deducted before the split — is available in Settings and would
                        change every partner's figure (scope of work §10.1).
                    </p>
                @endif
            </div>

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
                        <thead><tr><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                        <tbody>
                            @foreach ($run->journal->lines as $line)
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

            @if ($run->payments->isNotEmpty())
                <div class="card">
                    <div class="card-head"><div class="card-title">Payments made</div></div>
                    <table class="table table-compact">
                        <thead><tr><th>Date</th><th>From</th><th>Reference</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($run->payments as $payment)
                                <tr>
                                    <td class="text-muted">{{ $payment->payment_date->format('j M Y') }}</td>
                                    <td class="text-muted">{{ $payment->sourceAccount->displayName() }}</td>
                                    <td class="text-muted">{{ $payment->reference ?: '—' }}</td>
                                    <td class="num"><x-money :value="$payment->amount"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @can('manage-revenue-share')
                @if (! $run->isDraft() && $run->outstandingAmount() > 0)
                    <form method="POST" action="{{ route('revenue-share.pay', $run) }}" class="card">
                        @csrf
                        <div class="card-head">
                            <div>
                                <div class="card-title">Pay {{ $run->partner_name }}</div>
                                <div class="card-sub">
                                    {{ \App\Support\Money::format($run->outstandingAmount(), true) }} outstanding in 2040.
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 p-5 sm:grid-cols-4">
                            <div>
                                <label class="label" for="payment_date">Date</label>
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
                            <div class="sm:col-span-4">
                                <label class="label" for="reference">Reference</label>
                                <input id="reference" name="reference" class="input">
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-rule px-5 py-4">
                            <button class="btn btn-primary btn-sm">Record payment</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>

        <div class="card card-pad self-start">
            <div class="eyebrow mb-3">Run</div>
            <dl class="flex flex-col gap-2.5 text-xs">
                @foreach ([
                    'Reference' => $run->reference,
                    'Period' => $run->period->label(),
                    'Project' => $run->project?->name ?? 'All projects',
                    'Partner' => $run->partner_name,
                    'Basis' => $run->basis->label(),
                    'Percentage' => rtrim(rtrim(number_format($run->share_percent, 2), '0'), '.').'%',
                    'Posted' => $run->posted_at?->format('j M Y H:i'),
                ] as $label => $value)
                    <div class="flex justify-between gap-3">
                        <dt class="shrink-0 text-faint">{{ $label }}</dt>
                        <dd class="text-end text-body">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
@endsection
