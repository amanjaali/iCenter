<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope of work §5.2 — a declaration of profit to the partners for an explicit
 * date range, so a partner can see which months a payment covers.
 */
class DistributionRun extends Model
{
    use Auditable;

    protected $fillable = [
        'reference', 'title', 'period_start', 'period_end', 'period_id',
        'gross_revenue', 'direct_costs', 'operating_expenses', 'net_profit',
        'retained_amount', 'distributable_amount', 'distributed_amount',
        'status', 'journal_id', 'notes', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_revenue' => 'decimal:2',
            'direct_costs' => 'decimal:2',
            'operating_expenses' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'retained_amount' => 'decimal:2',
            'distributable_amount' => 'decimal:2',
            'distributed_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DistributionLine::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->lines()->sum('outstanding_amount'), 2);
    }

    public function periodLabel(): string
    {
        return $this->period_start->format('M Y') === $this->period_end->format('M Y')
            ? $this->period_start->format('F Y')
            : $this->period_start->format('M Y').' – '.$this->period_end->format('M Y');
    }
}
