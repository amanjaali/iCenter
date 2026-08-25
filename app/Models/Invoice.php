<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\ProrationMethod;
use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * Scope of work §4.3 — one invoice per bus company per month, generated from
 * the registry.
 */
class Invoice extends Model
{
    use Auditable;

    protected $fillable = [
        'number', 'bus_company_id', 'academic_year_id', 'period_id', 'billing_month',
        'issue_date', 'due_date', 'subtotal', 'discount_amount', 'total', 'amount_paid',
        'balance_due', 'student_count', 'billable_units', 'proration_method', 'status',
        'is_deferred', 'revenue_recognised', 'recognised_on', 'recognition_journal_id',
        'journal_id', 'notes', 'created_by', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'recognised_on' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'billable_units' => 'decimal:4',
            'status' => DocumentStatus::class,
            'proration_method' => ProrationMethod::class,
            'is_deferred' => 'boolean',
            'revenue_recognised' => 'boolean',
        ];
    }

    public function busCompany(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_no');
    }

    public function studentDetails(): HasManyThrough
    {
        return $this->hasManyThrough(InvoiceLineStudent::class, InvoiceLine::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function recognitionJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'recognition_journal_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isVoid(): bool
    {
        return $this->status === DocumentStatus::Void;
    }

    public function isSettled(): bool
    {
        return $this->status === DocumentStatus::Paid;
    }

    public function isOverdue(): bool
    {
        return ! $this->isSettled()
            && ! $this->isDraft()
            && ! $this->isVoid()
            && $this->due_date->isPast();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(Carbon::today()) : 0;
    }

    /** Recalculate paid/outstanding from allocations and set the status. */
    public function refreshPaymentState(): void
    {
        $paid = Money::round(
            $this->allocations()
                ->whereHas('payment', fn (Builder $q) => $q->where('status', 'posted'))
                ->sum('amount')
        );

        $balance = Money::round((float) $this->total - $paid);

        $status = match (true) {
            $this->status === DocumentStatus::Void => DocumentStatus::Void,
            $this->status === DocumentStatus::Draft => DocumentStatus::Draft,
            Money::isZero($balance) => DocumentStatus::Paid,
            $paid > 0 => DocumentStatus::PartiallyPaid,
            default => DocumentStatus::Issued,
        };

        $this->forceFill([
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'status' => $status,
        ])->save();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Void->value])
            ->where('balance_due', '>', 0);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Void->value]);
    }

    public function scopeForMonth(Builder $query, Carbon $month): Builder
    {
        return $query->whereDate('billing_month', $month->copy()->startOfMonth());
    }
}
