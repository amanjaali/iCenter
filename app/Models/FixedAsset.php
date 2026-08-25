<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Scope of work §7 — capital expenditure capitalised and depreciated.
 */
class FixedAsset extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'category', 'asset_account_id', 'accumulated_depreciation_account_id',
        'depreciation_expense_account_id', 'department_id', 'project_id', 'expense_id',
        'acquisition_date', 'depreciation_start_date', 'cost', 'salvage_value',
        'useful_life_months', 'method', 'accumulated_depreciation', 'status',
        'disposal_date', 'disposal_amount', 'disposal_journal_id', 'serial_number',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'depreciation_start_date' => 'date',
            'disposal_date' => 'date',
            'cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'disposal_amount' => 'decimal:2',
        ];
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function depreciationLines(): HasMany
    {
        return $this->hasMany(DepreciationLine::class);
    }

    public function netBookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }

    public function depreciableAmount(): float
    {
        return round((float) $this->cost - (float) $this->salvage_value, 2);
    }

    /** Straight line: an equal charge each month of the useful life. */
    public function monthlyCharge(): float
    {
        if ($this->useful_life_months < 1) {
            return 0.0;
        }

        return round($this->depreciableAmount() / $this->useful_life_months, 2);
    }

    /**
     * The charge due for a given month, capped so the asset never depreciates
     * past its salvage value — the final month absorbs the rounding remainder.
     */
    public function chargeFor(Carbon $month): float
    {
        if ($this->status !== 'active') {
            return 0.0;
        }

        $monthEnd = $month->copy()->endOfMonth();

        if ($this->depreciation_start_date->greaterThan($monthEnd)) {
            return 0.0;
        }

        $remaining = round($this->depreciableAmount() - (float) $this->accumulated_depreciation, 2);

        if ($remaining <= 0) {
            return 0.0;
        }

        return min($this->monthlyCharge(), $remaining);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
