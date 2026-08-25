<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope of work §2 — one of the five owners of ALLVA.
 */
class Partner extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'name_ar', 'email', 'phone', 'ownership_percent',
        'user_id', 'capital_account_id', 'joined_on', 'left_on', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percent' => 'decimal:4',
            'joined_on' => 'date',
            'left_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'capital_account_id');
    }

    public function distributionLines(): HasMany
    {
        return $this->hasMany(DistributionLine::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class);
    }

    public function ownershipChanges(): HasMany
    {
        return $this->hasMany(OwnershipChangeLog::class)->latest('effective_date');
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()->take(2)
            ->map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }

    /** Total declared, total paid and what is still owed to this partner. */
    public function outstandingAmount(): float
    {
        return round((float) $this->distributionLines()->sum('outstanding_amount'), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
