<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'department_id', 'project_id', 'salary_account_id',
        'base_salary', 'overtime', 'bonus', 'allowances', 'gross',
        'employee_ss', 'income_tax', 'advances_deducted', 'other_deductions', 'net',
        'employer_ss', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'overtime' => 'decimal:2',
            'bonus' => 'decimal:2',
            'allowances' => 'decimal:2',
            'gross' => 'decimal:2',
            'employee_ss' => 'decimal:2',
            'income_tax' => 'decimal:2',
            'advances_deducted' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'employer_ss' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function salaryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'salary_account_id');
    }

    public function totalDeductions(): float
    {
        return round(
            (float) $this->employee_ss + (float) $this->income_tax
            + (float) $this->advances_deducted + (float) $this->other_deductions,
            2
        );
    }
}
