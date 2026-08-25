@extends('layouts.app')
@section('title', 'Chart of accounts')
@section('subtitle', 'SCOPE OF WORK §6 · '.$accounts->count().' ACCOUNTS')

@section('actions')
    @can('manage-chart-of-accounts')
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-3.5"/> Add account
        </a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[200px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Code or name">
        </div>
        <div class="min-w-[200px]">
            <label class="label" for="class">Class</label>
            <select id="class" name="class" class="select">
                <option value="">All classes</option>
                @foreach ($classes as $value => $label)
                    <option value="{{ $value }}" @selected(request('class') == $value)>{{ $value }} — {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="from">Balances from</label>
            <input id="from" name="from" type="date" class="input" value="{{ $filters->from->toDateString() }}">
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="to">To</label>
            <input id="to" name="to" type="date" class="input" value="{{ $filters->to->toDateString() }}">
        </div>
        <button class="btn btn-primary"><x-icon name="filter" class="size-3.5"/> Apply</button>
        <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    @foreach ($accounts->groupBy('class') as $class => $group)
        <div class="card mb-4 overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">{{ $class }} — {{ $classes[$class] ?? '' }}</div>
                    <div class="card-sub">{{ $group->count() }} accounts · movement {{ $filters->label() }}</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th class="w-24">Code</th>
                            <th>Account</th>
                            <th>Classification</th>
                            <th class="num">Debit</th>
                            <th class="num">Credit</th>
                            <th class="num">Movement</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group as $account)
                            @php
                                $movement = $movements[$account->id] ?? null;
                                $balance = $movement
                                    ? $account->signedBalance($movement->debit, $movement->credit)
                                    : 0.0;
                            @endphp
                            <tr class="{{ $account->is_active ? '' : 'opacity-55' }}">
                                <td>
                                    <a href="{{ route('accounts.show', $account) }}" class="money text-xs hover:text-signal">
                                        {{ $account->code }}
                                    </a>
                                </td>
                                <td>
                                    {{ $account->name }}
                                    @if ($account->name_ar)
                                        <div class="text-[11px] text-faint" dir="rtl">{{ $account->name_ar }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1">
                                        @if ($account->subtype)
                                            <span class="badge badge-neutral">{{ $account->subtype->label() }}</span>
                                        @endif
                                        @if ($account->is_system)
                                            <span class="badge badge-info" title="Referenced by the posting engine by code">System</span>
                                        @endif
                                        @if (! $account->is_postable)
                                            <span class="badge badge-neutral">Heading</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="num">{{ $movement ? \App\Support\Money::format($movement->debit) : '—' }}</td>
                                <td class="num">{{ $movement ? \App\Support\Money::format($movement->credit) : '—' }}</td>
                                <td class="num"><x-money :value="$balance" accounting/></td>
                                <td class="text-end">
                                    @can('manage-chart-of-accounts')
                                        <a href="{{ route('accounts.edit', $account) }}" class="btn btn-ghost btn-sm">
                                            <x-icon name="edit" class="size-3.5"/>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
