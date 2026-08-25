<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One accounting month. A journal cannot be posted into a period that is not
 * open — this is what stops a closed month being quietly restated.
 */
class Period extends Model
{
    use Auditable;

    protected $fillable = [
        'fiscal_year_id', 'code', 'start_date', 'end_date', 'status', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open;
    }

    public function label(): string
    {
        return $this->start_date->format('F Y');
    }

    public function shortLabel(): string
    {
        return $this->start_date->format('M Y');
    }

    /** The period a given date falls in, or null if none has been created. */
    public static function forDate(Carbon|string $date): ?self
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PeriodStatus::Open->value);
    }

    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereDate('start_date', '>=', $from)->whereDate('end_date', '<=', $to);
    }
}
