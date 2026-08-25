@extends('layouts.app')
@section('title', $employee->exists ? 'Edit '.$employee->name : 'New employee')

@section('content')
    <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}"
          class="mx-auto flex max-w-3xl flex-col gap-4">
        @csrf
        @if ($employee->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-head"><div class="card-title">Employee</div></div>
            <div class="grid gap-4 p-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="code">Code</label>
                    <input id="code" name="code" class="input money @error('code') input-invalid @enderror"
                           value="{{ old('code', $employee->code) }}" required placeholder="EMP-001">
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" class="input @error('name') input-invalid @enderror"
                           value="{{ old('name', $employee->name) }}" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="label" for="name_ar">Name in Arabic</label>
                    <input id="name_ar" name="name_ar" class="input" dir="rtl" value="{{ old('name_ar', $employee->name_ar) }}">
                </div>
                <div>
                    <label class="label" for="department_id">Cost centre</label>
                    <select id="department_id" name="department_id" class="select" required>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $employee->department_id) == $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="salary_account_id">Salary account</label>
                    <select id="salary_account_id" name="salary_account_id" class="select" required>
                        @foreach ($salaryAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('salary_account_id', $employee->salary_account_id) == $account->id)>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Scope of work §6.6 — salaries split by function.</div>
                </div>
                <div>
                    <label class="label" for="project_id">Project</label>
                    <select id="project_id" name="project_id" class="select">
                        <option value="">Company overhead</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $employee->project_id) == $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Left blank, the cost is spread as overhead.</div>
                </div>
                <div>
                    <label class="label" for="position">Position</label>
                    <input id="position" name="position" class="input" value="{{ old('position', $employee->position) }}">
                </div>
                <div>
                    <label class="label" for="employment_type">Employment type</label>
                    <select id="employment_type" name="employment_type" class="select">
                        @foreach (['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('employment_type', $employee->employment_type) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="select">
                        @foreach (['active' => 'Active', 'suspended' => 'Suspended', 'left' => 'Left'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('status', $employee->status) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="hire_date">Hired on</label>
                    <input id="hire_date" name="hire_date" type="date" class="input" required
                           value="{{ old('hire_date', $employee->hire_date?->toDateString() ?? now()->toDateString()) }}">
                </div>
                <div>
                    <label class="label" for="end_date">End date</label>
                    <input id="end_date" name="end_date" type="date" class="input"
                           value="{{ old('end_date', $employee->end_date?->toDateString()) }}">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">Pay</div>
                    <div class="card-sub">These figures pre-fill each monthly run; individual lines can then be adjusted.</div>
                </div>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-4">
                <div>
                    <label class="label" for="base_salary">Base salary</label>
                    <input id="base_salary" name="base_salary" type="number" step="1" min="0" required
                           class="input input-num" value="{{ old('base_salary', $employee->base_salary ?? 0) }}">
                </div>
                <div>
                    <label class="label" for="allowances">Allowances</label>
                    <input id="allowances" name="allowances" type="number" step="1" min="0"
                           class="input input-num" value="{{ old('allowances', $employee->allowances ?? 0) }}">
                </div>
                <div>
                    <label class="label" for="employee_ss_rate">Employee SS %</label>
                    <input id="employee_ss_rate" name="employee_ss_rate" type="number" step="0.001" min="0" max="100" required
                           class="input input-num" value="{{ old('employee_ss_rate', $employee->employee_ss_rate ?? 5) }}">
                </div>
                <div>
                    <label class="label" for="employer_ss_rate">Employer SS %</label>
                    <input id="employer_ss_rate" name="employer_ss_rate" type="number" step="0.001" min="0" max="100" required
                           class="input input-num" value="{{ old('employer_ss_rate', $employee->employer_ss_rate ?? 12) }}">
                    <div class="hint">Charged to 6070.</div>
                </div>
                <div>
                    <label class="label" for="bank_name">Bank</label>
                    <input id="bank_name" name="bank_name" class="input" value="{{ old('bank_name', $employee->bank_name) }}">
                </div>
                <div>
                    <label class="label" for="bank_account">Bank account</label>
                    <input id="bank_account" name="bank_account" class="input money" value="{{ old('bank_account', $employee->bank_account) }}">
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $employee->phone) }}">
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $employee->email) }}">
                </div>
                <div class="sm:col-span-4">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="textarea">{{ old('notes', $employee->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('employees.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $employee->exists ? 'Save changes' : 'Add employee' }}</button>
        </div>
    </form>
@endsection
