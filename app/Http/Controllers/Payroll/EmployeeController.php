<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $employees = Employee::query()
            ->with(['department', 'salaryAccount', 'project'])
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('position', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('payroll.employees.index', [
            'employees' => $employees,
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'monthlyCost' => Employee::active()->get()
                ->sum(fn (Employee $e) => (float) $e->base_salary + (float) $e->allowances
                    + ((float) $e->base_salary + (float) $e->allowances) * (float) $e->employer_ss_rate / 100),
        ]);
    }

    public function create()
    {
        $this->authorize('manage-payroll');

        return view('payroll.employees.form', $this->formData() + [
            'employee' => new Employee([
                'status' => 'active',
                'employment_type' => 'full_time',
                'hire_date' => now()->toDateString(),
                'employee_ss_rate' => 5,
                'employer_ss_rate' => 12,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-payroll');

        $employee = Employee::create($this->validated($request));

        return redirect()->route('employees.index')->with('success', "{$employee->name} has been added to the register.");
    }

    public function edit(Employee $employee)
    {
        $this->authorize('manage-payroll');

        return view('payroll.employees.form', $this->formData() + ['employee' => $employee]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorize('manage-payroll');

        $employee->update($this->validated($request, $employee));

        return redirect()->route('employees.index')->with('success', "{$employee->name} has been updated.");
    }

    private function formData(): array
    {
        return [
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'projects' => Project::where('is_active', true)->orderBy('name')->get(),
            // Class 6000 salary accounts only: an employee's pay belongs in one
            // of 6010-6040, not anywhere in the chart.
            'salaryAccounts' => Account::whereIn('code', ['6010', '6020', '6030', '6040'])->orderBy('code')->get(),
        ];
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:employees,code'.($employee ? ",{$employee->id}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'salary_account_id' => ['required', 'exists:accounts,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'hire_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'employee_ss_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'employer_ss_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,suspended,left'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
