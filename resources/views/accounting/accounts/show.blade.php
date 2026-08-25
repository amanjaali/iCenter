@extends('layouts.app')
@section('title', $account->code.' — '.$account->name)
@section('subtitle', strtoupper($account->type->label().($account->subtype ? ' · '.$account->subtype->label() : '')))

@section('actions')
    <a href="{{ route('reports.general-ledger', ['account_id' => $account->id] + $filters->toArray()) }}" class="btn btn-secondary">
        <x-icon name="book" class="size-3.5"/> Ledger report
    </a>
    @can('manage-chart-of-accounts')
        <a href="{{ route('accounts.edit', $account) }}" class="btn btn-primary">Edit</a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[140px]">
            <label class="label" for="from">From</label>
            <input id="from" name="from" type="date" class="input" value="{{ $filters->from->toDateString() }}">
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="to">To</label>
            <input id="to" name="to" type="date" class="input" value="{{ $filters->to->toDateString() }}">
        </div>
        <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Apply</button>
    </form>

    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Opening balance" :value="\App\Support\Money::accounting($ledger['opening'])" accent="#98A2B3"
               :delta="'As at '.$filters->from->format('j M Y')"/>
        <x-kpi label="Movement" :value="\App\Support\Money::accounting($ledger['closing'] - $ledger['opening'])"
               accent="#00C2E8" :delta="$ledger['rows']->count().' entries'"/>
        <x-kpi label="Closing balance" :value="\App\Support\Money::accounting($ledger['closing'])" accent="#0A1F3D"
               :delta="'As at '.$filters->to->format('j M Y')"/>
    </div>

    <div class="card overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Ledger detail</div>
                <div class="card-sub">
                    Posted entries only, {{ $filters->label() }}. Balances run in the account's own
                    direction ({{ $account->normal_balance->value }} positive).
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Cost centre</th>
                        <th>Project</th>
                        <th class="num">Debit</th>
                        <th class="num">Credit</th>
                        <th class="num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-mist">
                        <td colspan="7" class="text-muted">Opening balance</td>
                        <td class="num"><x-money :value="$ledger['opening']" accounting/></td>
                    </tr>
                    @forelse ($ledger['rows'] as $row)
                        <tr>
                            <td class="whitespace-nowrap text-muted">{{ $row->date->format('j M Y') }}</td>
                            <td>
                                <a href="{{ route('journals.show', $row->journal_id) }}" class="money text-xs hover:text-signal">
                                    {{ $row->reference }}
                                </a>
                            </td>
                            <td class="max-w-[300px] truncate text-muted" title="{{ $row->description ?: $row->memo }}">
                                {{ $row->description ?: $row->memo }}
                            </td>
                            <td class="text-muted">{{ $row->department ?? '—' }}</td>
                            <td class="text-muted">{{ $row->project ?? '—' }}</td>
                            <td class="num">{{ $row->debit > 0 ? \App\Support\Money::format($row->debit) : '' }}</td>
                            <td class="num">{{ $row->credit > 0 ? \App\Support\Money::format($row->credit) : '' }}</td>
                            <td class="num"><x-money :value="$row->balance" accounting/></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No entries in this period"
                            message="Nothing has been posted to this account within the chosen dates."/></td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7">Closing balance</td>
                        <td class="num"><x-money :value="$ledger['closing']" accounting/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
