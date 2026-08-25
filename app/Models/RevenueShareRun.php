<?php

namespace App\Models;

use App\Enums\RevenueShareBasis;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope of work §5.1 — Cyber Gate's 50% for one period.
 */
class RevenueShareRun extends Model
{
    use Auditable;

    protected $fillable = [
        'reference', 'period_id', 'project_id', 'partner_name', 'basis', 'share_percent',
        'gross_revenue', 'operating_expenses', 'revenue_base', 'share_amount',
        'status', 'journal_id', 'paid_amount', 'notes', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'basis' => RevenueShareBasis::class,
            'share_percent' => 'decimal:4',
            'gross_revenue' => 'decimal:2',
            'operating_expenses' => 'decimal:2',
            'revenue_base' => 'decimal:2',
            'share_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RevenueSharePayment::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->share_amount - (float) $this->paid_amount, 2);
    }
}
