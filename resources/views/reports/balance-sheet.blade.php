@extends('layouts.app')
@section('title', 'Balance sheet')
@section('subtitle', 'AS AT '.strtoupper($report['as_at']->format('j F Y')))

@section('content')
    <x-report-header title="Balance sheet" :subtitle="'As at '.$report['as_at']->format('j F Y')"/>

    <x-page-filters :action="route('reports.balance-sheet')" :filters="$filters"
                    :projects="$projects" :departments="$departments"/>

    @unless ($report['totals']['balanced'])
        <div class="mt-4 flex items-start gap-2.5 rounded-md border border-negative/25 bg-negative-soft px-4 py-3 text-xs text-negative">
            <x-icon name="alert" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                The balance sheet is out by {{ \App\Support\Money::format($report['totals']['difference'], true) }}.
                This should never happen with a balanced ledger — check the trial balance and look for an entry
                posted outside the filters applied here.
            </div>
        </div>
    @endunless

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-head">
                <div class="card-title">Assets</div>
                <x-money :value="$report['totals']['assets']" class="text-sm font-medium"/>
            </div>
            <table class="table table-compact">
                <tbody>
                    @forelse ($report['assets']['accounts'] as $account)
                        <tr>
                            <td class="w-20"><span class="money text-xs text-muted">{{ $account->code }}</span></td>
                            <td>
                                <a href="{{ route('accounts.show', $account->id) }}" class="hover:text-signal">{{ $account->name }}</a>
                                @if ($account->is_contra)<span class="badge badge-neutral ms-1.5">Contra</span>@endif
                            </td>
                            <td class="num"><x-money :value="$account->natural" accounting/></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-faint">No assets on the books</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><td colspan="2">Total assets</td>
                        <td class="num"><x-money :value="$report['totals']['assets']" accounting/></td></tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-col gap-4">
            <div class="card overflow-hidden">
                <div class="card-head">
                    <div class="card-title">Liabilities</div>
                    <x-money :value="$report['totals']['liabilities']" class="text-sm font-medium"/>
                </div>
                <table class="table table-compact">
                    <tbody>
                        @forelse ($report['liabilities']['accounts'] as $account)
                            <tr>
                                <td class="w-20"><span class="money text-xs text-muted">{{ $account->code }}</span></td>
                                <td>
                                    <a href="{{ route('accounts.show', $account->id) }}" class="hover:text-signal">{{ $account->name }}</a>
                                </td>
                                <td class="num"><x-money :value="$account->natural" accounting/></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-faint">No liabilities on the books</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2">Total liabilities</td>
                            <td class="num"><x-money :value="$report['totals']['liabilities']" accounting/></td></tr>
                    </tfoot>
                </table>
            </div>

            <div class="card overflow-hidden">
                <div class="card-head">
                    <div class="card-title">Equity</div>
                    <x-money :value="$report['totals']['equity']" class="text-sm font-medium"/>
                </div>
                <table class="table table-compact">
                    <tbody>
                        @foreach ($report['equity']['accounts'] as $account)
                            <tr>
                                <td class="w-20"><span class="money text-xs text-muted">{{ $account->code }}</span></td>
                                <td>
                                    <a href="{{ route('accounts.show', $account->id) }}" class="hover:text-signal">{{ $account->name }}</a>
                                    @if ($account->is_contra)<span class="badge badge-neutral ms-1.5">Contra</span>@endif
                                </td>
                                <td class="num"><x-money :value="$account->natural" accounting/></td>
                            </tr>
                        @endforeach
                        @if (! \App\Support\Money::isZero($report['equity']['retained_brought_forward']))
                            <tr>
                                <td class="w-20"></td>
                                <td>Retained earnings brought forward
                                    <div class="text-[11px] text-faint">Profit accumulated before this fiscal year</div>
                                </td>
                                <td class="num"><x-money :value="$report['equity']['retained_brought_forward']" accounting/></td>
                            </tr>
                        @endif
                        <tr>
                            <td class="w-20"></td>
                            <td>Current year profit
                                <div class="text-[11px] text-faint">Computed from the ledger, before any closing entry</div>
                            </td>
                            <td class="num"><x-money :value="$report['equity']['current_year_profit']" accounting/></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2">Total equity</td>
                            <td class="num"><x-money :value="$report['totals']['equity']" accounting/></td></tr>
                    </tfoot>
                </table>
            </div>

            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium">Liabilities and equity</span>
                    <x-money :value="$report['totals']['liabilities_and_equity']" class="text-sm font-medium"/>
                </div>
                <div class="mt-2 flex items-center justify-between border-t border-rule pt-2">
                    <span class="text-xs text-muted">Difference against assets</span>
                    <span class="flex items-center gap-2">
                        @if ($report['totals']['balanced'])
                            <x-icon name="check" class="size-3.5 text-positive"/>
                            <span class="text-xs text-positive">Balanced</span>
                        @else
                            <x-money :value="$report['totals']['difference']" class="text-xs"/>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>
@endsection
