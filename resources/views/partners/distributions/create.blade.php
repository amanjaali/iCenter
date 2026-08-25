@extends('layouts.app')
@section('title', 'New partner distribution')

@section('content')
    {{-- Preview first: the figures come straight from the ledger, so what is
         declared can be traced to the income statement for the same window. --}}
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[150px]">
            <label class="label" for="from">Period from</label>
            <input id="from" name="from" type="date" class="input" value="{{ $from->toDateString() }}">
        </div>
        <div class="min-w-[150px]">
            <label class="label" for="to">Period to</label>
            <input id="to" name="to" type="date" class="input" value="{{ $to->toDateString() }}">
        </div>
        <div class="min-w-[170px]">
            <label class="label" for="retained">Retain in the company</label>
            <input id="retained" name="retained" type="number" step="1" min="0" class="input input-num" value="{{ (int) $retained }}">
        </div>
        <button class="btn btn-secondary"><x-icon name="refresh" class="size-3.5"/> Recalculate</button>
    </form>

    @if (abs($totalOwnership - 100) > 0.0001)
        <div class="mb-4 flex items-start gap-2.5 rounded-md border border-caution/25 bg-caution-soft px-4 py-3 text-xs text-caution">
            <x-icon name="alert" class="mt-px size-4 shrink-0"/>
            <div class="leading-relaxed">
                Ownership totals {{ rtrim(rtrim(number_format($totalOwnership, 4), '0'), '.') }}%, not 100%.
                The full distributable amount is still allocated, but by relative weight rather than the
                percentages shown.
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="card card-pad">
            <div class="eyebrow mb-4">Profit for {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}</div>
            <x-stat-row label="Revenue" :value="$calculation['gross_revenue']"/>
            <x-stat-row label="Direct costs" :value="-$calculation['direct_costs']" indent/>
            <x-stat-row label="Operating expenses" :value="-$calculation['operating_expenses']" indent/>
            <x-stat-row label="Net profit" :value="$calculation['net_profit']" emphasis/>
            <x-stat-row label="Retained in the company" :value="-$calculation['retained']" indent/>
            <x-stat-row label="Distributable to partners" :value="$calculation['distributable']" emphasis/>

            <p class="mt-4 border-t border-rule pt-4 text-[11px] leading-relaxed text-faint">
                These figures come from the posted ledger for the same window as the income statement.
                Direct costs already include {{ config('allva.settings_defaults')['revenue_share.partner_name'] ?? 'the revenue share partner' }}'s
                share where it has been posted.
            </p>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Each partner's share</div>
                    <div class="card-sub">
                        Allocated by largest remainder, so the parts add back to the distributable amount exactly.
                    </div>
                </div>
            </div>
            <table class="table">
                <thead><tr><th>Partner</th><th class="num">Ownership</th><th class="num">Share</th></tr></thead>
                <tbody>
                    @foreach ($calculation['shares'] as $share)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <span class="flex size-[24px] shrink-0 items-center justify-center rounded-full bg-mist
                                                 font-display text-[10px] font-medium text-navy">
                                        {{ $share['partner']->initials() }}
                                    </span>
                                    {{ $share['partner']->name }}
                                </div>
                            </td>
                            <td class="num">{{ rtrim(rtrim(number_format($share['percent'], 4), '0'), '.') }}%</td>
                            <td class="num"><x-money :value="$share['amount']"/></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="num">{{ rtrim(rtrim(number_format($calculation['total_percent'], 4), '0'), '.') }}%</td>
                        <td class="num"><x-money :value="$calculation['shares']->sum('amount')"/></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('distributions.store') }}" class="card mt-4">
        @csrf
        <input type="hidden" name="from" value="{{ $from->toDateString() }}">
        <input type="hidden" name="to" value="{{ $to->toDateString() }}">
        <input type="hidden" name="retained" value="{{ (int) $retained }}">
        <div class="flex flex-wrap items-end justify-between gap-3 p-5">
            <div class="min-w-[280px] flex-1">
                <label class="label" for="title">Title (optional)</label>
                <input id="title" name="title" class="input"
                       placeholder="{{ $from->format('M') }} – {{ $to->format('M Y') }} partner distribution">
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('distributions.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" @disabled($calculation['distributable'] <= 0)>
                    Prepare draft distribution
                </button>
            </div>
        </div>
        @if ($calculation['distributable'] <= 0)
            <div class="border-t border-rule px-5 py-3 text-xs text-muted">
                There is no distributable profit for this window, so there is nothing to declare.
            </div>
        @endif
    </form>
@endsection
