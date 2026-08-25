@extends('layouts.app')
@section('title', 'Distribution '.$run->reference)
@section('subtitle', strtoupper($run->periodLabel().' · '.$run->status))

@section('actions')
    @can('manage-distributions')
        @if ($run->isDraft())
            <form method="POST" action="{{ route('distributions.destroy', $run) }}"
                  data-confirm="Delete this draft distribution? Nothing has been declared to the partners yet.">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Delete draft</button>
            </form>
            <x-confirm-form :action="route('distributions.post', $run)" class="btn btn-primary"
                confirm="Posting declares the profit to the partners: their drawings account (3060) is debited and partner distributions payable (2050) credited. Each partner's percentage is frozen at today's figure.">
                Declare and post
            </x-confirm-form>
        @endif
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
        <div class="flex flex-col gap-4">
            <div class="card overflow-hidden">
                <div class="card-head">
                    <div>
                        <div class="card-title">Partner shares</div>
                        <div class="card-sub">
                            Profit share earned, amount paid out, and amount still outstanding — per partner (§5.2).
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>Partner</th><th class="num">Ownership</th><th class="num">Share</th>
                                <th class="num">Paid</th><th class="num">Outstanding</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($run->lines as $line)
                                <tr x-data="{ paying: false }">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-mist
                                                         font-display text-[11px] font-medium text-navy">
                                                {{ $line->partner->initials() }}
                                            </span>
                                            <a href="{{ route('partners.show', $line->partner) }}" class="hover:text-signal">
                                                {{ $line->partner->name }}
                                            </a>
                                        </div>
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format($line->ownership_percent, 4), '0'), '.') }}%</td>
                                    <td class="num"><x-money :value="$line->share_amount"/></td>
                                    <td class="num"><x-money :value="$line->paid_amount" muted/></td>
                                    <td class="num"><x-money :value="$line->outstanding_amount" muted/></td>
                                    <td class="text-end">
                                        @can('manage-distributions')
                                            @if (! $run->isDraft() && (float) $line->outstanding_amount > 0)
                                                <button type="button" @click="paying = !paying" class="btn btn-secondary btn-sm">Pay</button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                                @can('manage-distributions')
                                    @if (! $run->isDraft() && (float) $line->outstanding_amount > 0)
                                        <tr x-show="paying" x-cloak>
                                            <td colspan="6" class="bg-mist/60">
                                                <form method="POST" action="{{ route('distributions.payout', $line) }}"
                                                      class="flex flex-wrap items-end gap-3">
                                                    @csrf
                                                    <div class="min-w-[140px]">
                                                        <label class="label">Date</label>
                                                        <input name="payout_date" type="date" class="input" required value="{{ now()->toDateString() }}">
                                                    </div>
                                                    <div class="min-w-[140px]">
                                                        <label class="label">Amount</label>
                                                        <input name="amount" type="number" step="1" min="1"
                                                               max="{{ (float) $line->outstanding_amount }}" class="input input-num" required
                                                               value="{{ (float) $line->outstanding_amount }}">
                                                    </div>
                                                    <div class="min-w-[190px]">
                                                        <label class="label">Paid from</label>
                                                        <select name="source_account_id" class="select" required>
                                                            @foreach ($cashAccounts as $account)
                                                                <option value="{{ $account->id }}">{{ $account->displayName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="min-w-[140px]">
                                                        <label class="label">Method</label>
                                                        <select name="method" class="select">
                                                            <option value="bank">Bank transfer</option>
                                                            <option value="cash">Cash</option>
                                                            <option value="transfer">Other transfer</option>
                                                        </select>
                                                    </div>
                                                    <div class="min-w-[150px] flex-1">
                                                        <label class="label">Reference</label>
                                                        <input name="reference" class="input">
                                                    </div>
                                                    <button class="btn btn-primary btn-sm">Record payout</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endcan
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="num">
                                    {{ rtrim(rtrim(number_format($run->lines->sum('ownership_percent'), 4), '0'), '.') }}%
                                </td>
                                <td class="num"><x-money :value="$run->lines->sum('share_amount')"/></td>
                                <td class="num"><x-money :value="$run->lines->sum('paid_amount')"/></td>
                                <td class="num"><x-money :value="$run->lines->sum('outstanding_amount')"/></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-4">How this was calculated</div>
                <x-stat-row label="Revenue" :value="$run->gross_revenue"/>
                <x-stat-row label="Direct costs" :value="-$run->direct_costs" indent/>
                <x-stat-row label="Operating expenses" :value="-$run->operating_expenses" indent/>
                <x-stat-row label="Net profit" :value="$run->net_profit" emphasis/>
                <x-stat-row label="Retained" :value="-$run->retained_amount" indent/>
                <x-stat-row label="Distributable" :value="$run->distributable_amount" emphasis/>
            </div>

            <div class="card card-pad">
                <div class="eyebrow mb-3">Distribution</div>
                <dl class="flex flex-col gap-2.5 text-xs">
                    @foreach ([
                        'Reference' => $run->reference,
                        'Title' => $run->title,
                        'Period covered' => $run->periodLabel(),
                        'From' => $run->period_start->format('j M Y'),
                        'To' => $run->period_end->format('j M Y'),
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
    </div>
@endsection
