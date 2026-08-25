<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Scope of work §4.2 — the effective-dated rate table. Never read a rate from
 * anywhere else: App\Services\Billing\RateResolver is the only sanctioned way
 * to answer "what did this company pay per student in this month".
 */
class RateCard extends Model
{
    use Auditable;

    protected $fillable = [
        'academic_year_id', 'bus_company_id', 'amount', 'period',
        'effective_from', 'effective_to', 'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function busCompany(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(RateChangeLog::class)->latest();
    }

    /** Invoice lines billed at this rate — a rate in use is never deleted. */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A rate with no bus company applies to every company. */
    public function isGeneral(): bool
    {
        return $this->bus_company_id === null;
    }

    public function appliesOn(Carbon $date): bool
    {
        return $this->is_active
            && $this->effective_from->lte($date)
            && ($this->effective_to === null || $this->effective_to->gte($date));
    }

    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }
}
