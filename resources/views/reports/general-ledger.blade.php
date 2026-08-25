@extends('layouts.app')
@section('title', 'General ledger')
@section('subtitle', strtoupper($account->code.' — '.$account->name))

@section('content')
    <x-report-header :title="'General ledger — '.$account->displayName()" :filters="$filters"/>

    <x-page-filters :action="route('reports.general-ledger')" :filters="$filters"
                    :projects="$projects" :departments="$departments">
        <div class="min-w-[260px]">
            <label class="label" for="account_id">Account</label>
            <select id="account_id" name="account_id" class="select">
                @foreach ($accounts as $option)
                    <option value="{{ $option->id }}" @selected($account->id === $option->id)>
                        {{ $option->code }} — {{ $option->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </x-page-filters>

    <div class="card mt-4 overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">{{ $account->displayName() }}</div>
                <div class="card-sub">
                    {{ $report['rows']->count() }} entries · balance runs {{ $account->normal_balance->value }}-positive
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-compact">
                <thead>
                    <tr><th>Date</th><th>Reference</th><th>Description</th><th>Cost centre</th>
                        <th>Project</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr>
                </thead>
                <tbody>
                    <tr class="bg-mist">
                        <td colspan="7" class="text-muted">Opening balance</td>
                        <td class="num"><x-money :value="$report['opening']" accounting/></td>
                    </tr>
                    @foreach ($report['rows'] as $row)
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
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7">Closing balance</td>
                        <td class="num"><x-money :value="$report['closing']" accounting/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
