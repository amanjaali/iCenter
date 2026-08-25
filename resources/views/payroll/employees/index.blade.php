@extends('layouts.app')
@section('title', 'Employees')
@section('subtitle', $employees->total().' ON THE REGISTER')

@section('actions')
    @can('manage-payroll')
        <a href="{{ route('employees.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-3.5"/> Add employee</a>
    @endcan
@endsection

@section('content')
    <div class="mb-4 grid gap-3.5 sm:grid-cols-3">
        <x-kpi label="Active employees" :value="number_format($employees->total())" accent="#0A1F3D" delta="On the register"/>
        <x-kpi label="Monthly cost" :value="\App\Support\Money::format($monthlyCost)" accent="#00C2E8"
               delta="Base, allowances and employer social security"/>
        <x-kpi label="Payroll report" value="§8.3" accent="#98A2B3"
               delta="Per employee and per department" :href="route('reports.payroll')"/>
    </div>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-3.5">
        <div class="min-w-[200px] flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" class="input" value="{{ request('q') }}" placeholder="Name, code or position">
        </div>
        <div class="min-w-[170px]">
            <label class="label" for="department_id">Cost centre</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">All</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[140px]">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All</option>
                @foreach (['active' => 'Active', 'suspended' => 'Suspended', 'left' => 'Left'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary"><x-icon name="search" class="size-3.5"/> Search</button>
        <a href="{{ route('employees.index') }}" class="btn btn-secondary">Reset</a>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Cost centre</th><th>Position</th><th>Salary account</th>
                        <th class="num">Base salary</th><th class="num">Allowances</th><th>Hired</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $employee->name }}</span>
                                <div class="eyebrow">{{ $employee->code }}</div>
                            </td>
                            <td class="text-muted">{{ $employee->department->name }}</td>
                            <td class="text-muted">{{ $employee->position ?: '—' }}</td>
                            <td class="text-muted">
                                <span class="money">{{ $employee->salaryAccount->code }}</span>
                            </td>
                            <td class="num"><x-money :value="$employee->base_salary"/></td>
                            <td class="num"><x-money :value="$employee->allowances" muted/></td>
                            <td class="whitespace-nowrap text-muted">{{ $employee->hire_date->format('j M Y') }}</td>
                            <td><x-badge :status="$employee->status" :tone="$employee->status === 'active' ? 'success' : 'neutral'"/></td>
                            <td class="text-end">
                                @can('manage-payroll')
                                    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-ghost btn-sm">
                                        <x-icon name="edit" class="size-3.5"/>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty title="No employees"
                            message="Scope of work §2 — ALLVA operates as a full company: it employs staff and pays salaries."/></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->hasPages())<div class="border-t border-rule px-5 py-3">{{ $employees->links() }}</div>@endif
    </div>
@endsection
