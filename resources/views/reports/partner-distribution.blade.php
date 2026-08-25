@extends('layouts.app')
@section('title', 'Partner distribution')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Partner distribution" :filters="$filters"/>

    <x-page-filters :action="route('reports.partner-distribution')" :filters="$filters"
                    :projects="$projects" :departments="$departments"/>

    <div class="mt-4 grid gap-4 xl:grid-cols-[360px_1fr]">
        <div class="card card-pad self-start">
            <div class="eyebrow mb-4">Profit for the period</div>
            <x-stat-row label="Revenue" :value="$report['profit']['revenue']"/>
            <x-stat-row label="Direct costs" :value="-$report['profit']['direct_costs']" indent/>
            <x-stat-row label="Gross profit" :value="$report['profit']['gross_profit']" emphasis/>
            <x-stat-row label="Operating expenses" :value="-$report['profit']['operating_expenses']" indent/>
            <x-stat-row label="Net profit after all expenses" :value="$report['profit']['net_profit']" emphasis/>

            <p class="mt-4 border-t border-rule pt-4 text-[11px] leading-relaxed text-faint">
                Scope of work §5.2 — profit is calculated after all operating expenses have been deducted, and
                the remaining net profit distributed among the partners. The declared figures on the right are
                what has actually been posted, which may differ if profit was retained.
            </p>
        </div>

        <div class="card overflow-hidden">
            <div class="card-head">
                <div>
                    <div class="card-title">Per partner</div>
                    <div class="card-sub">Share earned, amount paid out, and amount still outstanding.</div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr><th>Partner</th><th class="num">Ownership</th><th class="num">Declared</th>
                            <th class="num">Paid</th><th class="num">Outstanding</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-mist
                                                     font-display text-[11px] font-medium text-navy">
                                            {{ $row['partner']->initials() }}
                                        </span>
                                        <a href="{{ route('partners.show', $row['partner']) }}" class="hover:text-signal">
                                            {{ $row['partner']->name }}
                                        </a>
                                    </div>
                                </td>
                                <td class="num">{{ rtrim(rtrim(number_format($row['ownership_percent'], 4), '0'), '.') }}%</td>
                                <td class="num"><x-money :value="$row['declared']"/></td>
                                <td class="num"><x-money :value="$row['paid']" muted/></td>
                                <td class="num"><x-money :value="$row['outstanding']" muted/></td>
                            </tr>
                            @foreach ($row['lines'] as $line)
                                <tr class="bg-mist/50">
                                    <td class="ps-12 text-[11.5px] text-muted">
                                        <a href="{{ route('distributions.show', $line->run) }}" class="hover:text-signal">
                                            {{ $line->run->reference }} · {{ $line->run->periodLabel() }}
                                        </a>
                                    </td>
                                    <td class="num text-[11.5px] text-muted">
                                        {{ rtrim(rtrim(number_format($line->ownership_percent, 4), '0'), '.') }}%
                                    </td>
                                    <td class="num text-[11.5px] text-muted">{{ \App\Support\Money::format($line->share_amount) }}</td>
                                    <td class="num text-[11.5px] text-muted">{{ \App\Support\Money::format($line->paid_amount) }}</td>
                                    <td class="num text-[11.5px] text-muted">{{ \App\Support\Money::format($line->outstanding_amount) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="num">{{ rtrim(rtrim(number_format($report['totals']['ownership_percent'], 4), '0'), '.') }}%</td>
                            <td class="num"><x-money :value="$report['totals']['declared']"/></td>
                            <td class="num"><x-money :value="$report['totals']['paid']"/></td>
                            <td class="num"><x-money :value="$report['totals']['outstanding']"/></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection
