@extends('layouts.app')
@section('title', 'Payroll')
@section('subtitle', 'MONTHLY RUNS')

@section('actions')
    @can('manage-payroll')
        <form method="POST" action="{{ route('payroll.prepare') }}" class="flex items-center gap-2">
            @csrf
            <input name="month" type="month" class="input w-[150px]" value="{{ now()->format('Y-m') }}" required>
            <button class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Prepare run</button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="card-head">
            <div>
                <div class="card-title">Payroll runs</div>
                <div class="card-sub">
                    Prepared from the employee register, then edited line by line before posting.
                </div>
            </div>
            <a href="{{ route('reports.payroll') }}" class="btn btn-secondary btn-sm">Payroll report</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th><th>Month</th><th class="num">Employees</th>
                        <th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net</th>
                        <th class="num">Employer SS</th><th class="num">Paid</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('payroll.show', $run) }}" class="money text-xs hover:text-signal">{{ $run->reference }}</a>
                            </td>
                            <td class="whitespace-nowrap">{{ $run->payroll_month->format('F Y') }}</td>
                            <td class="num">{{ $run->lines_count }}</td>
                            <td class="num"><x-money :value="$run->gross_total"/></td>
                            <td class="num"><x-money :value="$run->deductions_total" muted/></td>
                            <td class="num"><x-money :value="$run->net_total"/></td>
                            <td class="num"><x-money :value="$run->employer_ss_total" muted/></td>
                            <td class="num"><x-money :value="$run->paid_amount" muted/></td>
                            <td>
                                <x-badge :status="$run->status"
                                         :tone="match($run->status) { 'paid' => 'success', 'posted' => 'info', 'approved' => 'warning', default => 'neutral' }"/>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty title="No payroll runs"
                            message="Scope of work §6.6 — salaries are charged to accounts 6010 to 6040 by function, with overtime, bonuses, allowances and the employer's social security contribution booked separately."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($runs->hasPages())<div class="border-t border-rule px-5 py-3">{{ $runs->links() }}</div>@endif
    </div>
@endsection
