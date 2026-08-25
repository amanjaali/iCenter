@extends('layouts.app')
@section('title', 'Cash flow statement')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Cash flow statement" :filters="$filters"/>

    <x-page-filters :action="route('reports.cash-flow')" :filters="$filters"
                    :projects="$projects" :departments="$departments"/>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="card card-pad">
            <div class="eyebrow mb-4">Indirect method</div>

            <div class="mb-1 mt-2 text-xs font-medium">Operating activities</div>
            <x-stat-row label="Net profit" :value="$report['operating']['net_profit']" indent/>
            <x-stat-row label="Depreciation and amortisation" :value="$report['operating']['depreciation']" indent/>
            @foreach ([
                'receivables' => 'Movement in receivables',
                'inventory' => 'Movement in inventory',
                'prepaid' => 'Movement in prepaid expenses',
                'payables' => 'Movement in accounts payable',
                'accrued_salaries' => 'Movement in accrued salaries',
                'payroll_taxes' => 'Movement in payroll taxes payable',
                'revenue_share_payable' => 'Movement in revenue share payable',
                'deferred_revenue' => 'Movement in deferred revenue',
                'other_accruals' => 'Movement in other accruals',
            ] as $key => $label)
                @if (! \App\Support\Money::isZero($report['operating'][$key]))
                    <x-stat-row :label="$label" :value="$report['operating'][$key]" indent/>
                @endif
            @endforeach
            <x-stat-row label="Cash from operating activities" :value="$report['operating']['total']" emphasis/>

            <div class="mb-1 mt-5 text-xs font-medium">Investing activities</div>
            <x-stat-row label="Purchase of fixed assets" :value="$report['investing']['asset_purchases']" indent/>
            <x-stat-row label="Cash from investing activities" :value="$report['investing']['total']" emphasis/>

            <div class="mb-1 mt-5 text-xs font-medium">Financing activities</div>
            @foreach ([
                'loans' => 'Loans and financing',
                'partner_capital' => 'Partner capital contributed',
                'drawings' => 'Partner drawings',
                'distributions_payable' => 'Movement in distributions payable',
            ] as $key => $label)
                @if (! \App\Support\Money::isZero($report['financing'][$key]))
                    <x-stat-row :label="$label" :value="$report['financing'][$key]" indent/>
                @endif
            @endforeach
            <x-stat-row label="Cash from financing activities" :value="$report['financing']['total']" emphasis/>
        </div>

        <div class="flex flex-col gap-4">
            <div class="card card-pad">
                <div class="eyebrow mb-4">Reconciliation</div>
                <x-stat-row label="Opening cash" :value="$report['summary']['opening_cash']"/>
                <x-stat-row label="Net movement" :value="$report['summary']['net_movement']" indent/>
                <x-stat-row label="Closing cash" :value="$report['summary']['closing_cash']" emphasis/>

                <div class="mt-3 border-t border-rule pt-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted">Unexplained</span>
                        @if (\App\Support\Money::isZero($report['summary']['unexplained']))
                            <span class="flex items-center gap-1.5 text-positive">
                                <x-icon name="check" class="size-3.5"/> Reconciled
                            </span>
                        @else
                            <x-money :value="$report['summary']['unexplained']"/>
                        @endif
                    </div>
                    @unless (\App\Support\Money::isZero($report['summary']['unexplained']))
                        <p class="mt-2 text-[11px] leading-relaxed text-muted">
                            The indirect build does not fully explain the cash movement. This usually means a
                            balance sheet account outside the ones listed above has moved — the general ledger
                            for the period will show which.
                        </p>
                    @endunless
                </div>
            </div>

            <div class="card card-pad">
                <div class="eyebrow mb-3">Reading this</div>
                <p class="text-[11px] leading-relaxed text-muted">
                    A rise in a receivable consumes cash and shows as negative; a rise in a payable releases it
                    and shows as positive. Depreciation is added back because it is charged to profit but moves
                    no cash.
                </p>
            </div>
        </div>
    </div>
@endsection
