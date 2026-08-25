<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'name_ar', 'department_id', 'project_id', 'salary_account_id',
        'position', 'employment_type', 'hire_date', 'end_date', 'base_salary',
        'allowances', 'employee_ss_rate', 'employer_ss_rate', 'bank_name',
        'bank_account', 'phone', 'email', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'end_date' => 'date',
            'base_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'employee_ss_rate' => 'decimal:3',
            'employer_ss_rate' => 'decimal:3',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function salaryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'salary_account_id');
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
