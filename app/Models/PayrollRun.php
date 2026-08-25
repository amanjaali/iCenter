<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use Auditable;

    protected $fillable = [
        'reference', 'period_id', 'payroll_month', 'payment_date', 'gross_total',
        'deductions_total', 'employer_ss_total', 'net_total', 'paid_amount',
        'status', 'journal_id', 'notes', 'created_by', 'approved_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'payroll_month' => 'date',
            'payment_date' => 'date',
            'gross_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'employer_ss_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return in_array($this->status, ['posted', 'paid'], true);
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->net_total - (float) $this->paid_amount, 2);
    }

    /** Total company cost: gross pay plus the employer's own contribution. */
    public function totalCost(): float
    {
        return round((float) $this->gross_total + (float) $this->employer_ss_total, 2);
    }
}
