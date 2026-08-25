<?php

namespace App\Models;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\Concerns\Auditable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A balanced double entry. Journals are never deleted once posted — §9.3
 * requires corrections to be made by reversal.
 */
class Journal extends Model
{
    use Auditable;

    protected $fillable = [
        'reference', 'journal_date', 'period_id', 'memo', 'source_type', 'source_id',
        'status', 'total_debit', 'total_credit', 'reversal_of_id', 'reversed_by_id',
        'reversal_reason', 'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'status' => JournalStatus::class,
            'source_type' => JournalSource::class,
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function isPosted(): bool
    {
        return $this->status === JournalStatus::Posted;
    }

    public function isDraft(): bool
    {
        return $this->status === JournalStatus::Draft;
    }

    public function isReversed(): bool
    {
        return $this->status === JournalStatus::Reversed;
    }

    public function isBalanced(): bool
    {
        return Money::equals($this->total_debit, $this->total_credit);
    }

    /** A reversal cannot itself be reversed, and neither can a draft. */
    public function canBeReversed(): bool
    {
        return $this->isPosted() && $this->reversed_by_id === null;
    }

    /** The document this journal was produced from, if it was automatic. */
    public function sourceDocument(): ?Model
    {
        if (! $this->source_id) {
            return null;
        }

        $class = match ($this->source_type) {
            JournalSource::Invoice, JournalSource::RevenueRecognition => Invoice::class,
            JournalSource::Payment => Payment::class,
            JournalSource::Expense => Expense::class,
            JournalSource::SupplierPayment => SupplierPayment::class,
            JournalSource::Payroll => PayrollRun::class,
            JournalSource::PayrollPayment => PayrollPayment::class,
            JournalSource::Depreciation => DepreciationRun::class,
            JournalSource::RevenueShare => RevenueShareRun::class,
            JournalSource::RevenueSharePayment => RevenueSharePayment::class,
            JournalSource::Distribution => DistributionRun::class,
            JournalSource::PartnerPayout => PartnerPayout::class,
            default => null,
        };

        return $class ? $class::find($this->source_id) : null;
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', JournalStatus::Posted->value);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('journal_date', [$from->toDateString(), $to->toDateString()]);
    }
}
