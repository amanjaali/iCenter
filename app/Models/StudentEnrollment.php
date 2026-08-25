<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The billable history of one student on one bus. Billing counts days from
 * these rows, which is what makes a mid-month join or exit chargeable and a
 * past invoice reproducible.
 */
class StudentEnrollment extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id', 'bus_id', 'bus_company_id', 'start_date', 'end_date',
        'start_reason', 'end_reason', 'is_billable', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_billable' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function busCompany(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class);
    }

    public function isOpen(): bool
    {
        return $this->end_date === null;
    }

    /**
     * Days of this enrollment that fall inside the given month, both ends
     * inclusive. Returns 0 when the enrollment does not overlap the month.
     */
    public function billableDaysIn(Carbon $monthStart, Carbon $monthEnd): int
    {
        $from = $this->start_date->greaterThan($monthStart) ? $this->start_date : $monthStart;
        $to = $this->end_date && $this->end_date->lessThan($monthEnd) ? $this->end_date : $monthEnd;

        if ($from->greaterThan($to)) {
            return 0;
        }

        return $from->diffInDays($to) + 1;
    }

    /** Enrollments overlapping the window at any point. */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from));
    }

    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('is_billable', true);
    }
}
