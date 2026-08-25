@extends('layouts.app')
@section('title', 'Expense report')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Expense report" :filters="$filters"/>

    <x-page-filters :action="route('reports.expenses')" :filters="$filters"
                    :projects="$projects" :departments="$departments">
        <div class="min-w-[130px]">
            <label class="label" for="nature">Nature</label>
            <select id="nature" name="nature" class="select">
                <option value="">All</option>
                <option value="fixed" @selected($filters->nature === 'fixed')>Fixed</option>
                <option value="variable" @selected($filters->nature === 'variable')>Variable</option>
            </select>
        </div>
    </x-page-filters>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Direct costs" :value="\App\Support\Money::format($report['totals']['direct_costs'])" accent="#0A1F3D"
               delta="Class 5000"/>
        <x-kpi label="Operating expenses" :value="\App\Support\Money::format($report['totals']['operating'])" accent="#00C2E8"
               delta="Classes 6000 to 9000"/>
        <x-kpi label="Fixed / variable"
               :value="\App\Support\Money::format($report['classification']['fixed']).' / '.\App\Support\Money::format($report['classification']['variable'])"
               accent="#98A2B3" delta="Scope of work §7"/>
        <x-kpi label="Capital spend" :value="\App\Support\Money::format($report['classification']['capex'])" accent="#0E7C5A"
               delta="Capitalised, not charged to the period"/>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-head"><div class="card-title">By account</div></div>
            <div class="max-h-[560px] overflow-y-auto">
                <table class="table table-compact">
                    <thead>
                        <tr><th class="w-20">Code</th><th>Account</th><th class="num">Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($report['by_account'] as $account)
                            <tr>
                                <td><a href="{{ route('accounts.show', $account->id) }}" class="money text-xs hover:text-signal">{{ $account->code }}</a></td>
                                <td>{{ $account->name }}</td>
                                <td class="num"><x-money :value="$account->natural"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-empty title="No expenses in this period"/></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2">Total</td><td class="num"><x-money :value="$report['totals']['total']"/></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div class="card overflow-hidden">
                <div class="card-head"><div class="card-title">By cost centre</div></div>
                <div class="overflow-x-auto">
                    <table class="table table-compact">
                        <thead>
                            <tr><th>Cost centre</th><th class="num">Direct</th><th class="num">Payroll</th>
                                <th class="num">Admin</th><th class="num">Vehicle</th><th class="num">Sales</th><th class="num">Total</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($report['by_department'] as $row)
                                <tr>
                                    <td>{{ $row['department']->name }}</td>
                                    <td class="num"><x-money :value="$row['direct_costs']" muted/></td>
                                    <td class="num"><x-money :value="$row['payroll']" muted/></td>
                                    <td class="num"><x-money :value="$row['administrative']" muted/></td>
                                    <td class="num"><x-money :value="$row['vehicle']" muted/></td>
                                    <td class="num"><x-money :value="$row['sales_other']" muted/></td>
                                    <td class="num"><x-money :value="$row['total']"/></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-faint">No expenses by cost centre</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="card-head"><div class="card-title">By month</div></div>
                <table class="table table-compact">
                    <thead><tr><th>Month</th><th class="num">Direct costs</th><th class="num">Operating</th><th class="num">Total</th></tr></thead>
                    <tbody>
                        @foreach ($report['by_month'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="num"><x-money :value="$row['direct_costs']" muted/></td>
                                <td class="num"><x-money :value="$row['operating']" muted/></td>
                                <td class="num"><x-money :value="$row['total']"/></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
