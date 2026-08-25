@extends('layouts.app')
@section('title', 'Payroll report')
@section('subtitle', strtoupper($filters->label()))

@section('content')
    <x-report-header title="Payroll report" :filters="$filters"/>

    <x-page-filters :action="route('reports.payroll')" :filters="$filters" :departments="$departments"/>

    <div class="mt-4 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Gross pay" :value="\App\Support\Money::format($report['totals']['gross'])" accent="#0A1F3D"
               :delta="$report['totals']['employees'].' employees across '.$report['runs']->count().' runs'"/>
        <x-kpi label="Deductions" :value="\App\Support\Money::format($report['totals']['deductions'])" accent="#98A2B3"
               delta="Withheld from gross"/>
        <x-kpi label="Net paid" :value="\App\Support\Money::format($report['totals']['net'])" accent="#00C2E8"
               delta="Taken home by staff"/>
        <x-kpi label="Total company cost" :value="\App\Support\Money::format($report['totals']['total_cost'])" accent="#0E7C5A"
               delta="Gross plus employer social security"/>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_400px]">
        <div class="card overflow-hidden">
            <div class="card-head"><div class="card-title">Per employee</div></div>
            <div class="overflow-x-auto">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Employee</th><th>Cost centre</th><th class="num">Months</th>
                            <th class="num">Base</th><th class="num">Variable</th><th class="num">Gross</th>
                            <th class="num">Deductions</th><th class="num">Net</th><th class="num">Total cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['by_employee'] as $row)
                            <tr>
                                <td>
                                    {{ $row['employee']?->name ?? '—' }}
                                    <div class="eyebrow">{{ $row['employee']?->code }}</div>
                                </td>
                                <td class="text-muted">{{ $row['department']?->name ?? '—' }}</td>
                                <td class="num">{{ $row['months'] }}</td>
                                <td class="num"><x-money :value="$row['base_salary']"/></td>
                                <td class="num">
                                    <x-money :value="$row['overtime'] + $row['bonus'] + $row['allowances']" muted/>
                                </td>
                                <td class="num"><x-money :value="$row['gross']"/></td>
                                <td class="num"><x-money :value="$row['deductions']" muted/></td>
                                <td class="num"><x-money :value="$row['net']"/></td>
                                <td class="num"><x-money :value="$row['total_cost']"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-empty title="No payroll in this period"/></td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">Total</td>
                            <td class="num"><x-money :value="$report['totals']['gross']"/></td>
                            <td class="num"><x-money :value="$report['totals']['deductions']"/></td>
                            <td class="num"><x-money :value="$report['totals']['net']"/></td>
                            <td class="num"><x-money :value="$report['totals']['total_cost']"/></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden self-start">
            <div class="card-head"><div class="card-title">Per cost centre</div></div>
            <table class="table table-compact">
                <thead>
                    <tr><th>Cost centre</th><th class="num">Staff</th><th class="num">Gross</th><th class="num">Total cost</th></tr>
                </thead>
                <tbody>
                    @forelse ($report['by_department'] as $row)
                        <tr>
                            <td>{{ $row['department']?->name ?? '—' }}</td>
                            <td class="num">{{ $row['employees'] }}</td>
                            <td class="num"><x-money :value="$row['gross']"/></td>
                            <td class="num"><x-money :value="$row['total_cost']"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-faint">No payroll in this period</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
