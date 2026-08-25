<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Scope of work §4.2 / §10.3.
 */
class AcademicYear extends Model
{
    use Auditable;

    protected $fillable = [
        'name', 'start_date', 'end_date', 'billing_mode', 'billable_months', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'billing_mode' => BillingMode::class,
            'billable_months' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Whether a given month is billed at all under this year's holiday rule
     * (§10.3). In 'continue' mode every month inside the year is billable.
     */
    public function isBillableMonth(Carbon $month): bool
    {
        if ($month->lt($this->start_date->copy()->startOfMonth()) || $month->gt($this->end_date)) {
            return false;
        }

        if ($this->billing_mode === BillingMode::Continue) {
            return true;
        }

        return in_array((int) $month->month, $this->billable_months ?? [], true);
    }

    /** The academic year a date belongs to, if one has been set up. */
    public static function forDate(Carbon $date): ?self
    {
        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public static function current(): ?self
    {
        return static::where('is_active', true)->first() ?? static::forDate(Carbon::today());
    }
}
