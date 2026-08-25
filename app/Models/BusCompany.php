<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Scope of work §3.1 — the top of the registry chain that feeds billing.
 */
class BusCompany extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'name_ar', 'contact_name', 'phone', 'email', 'address',
        'tax_number', 'payment_terms_days', 'contract_start', 'contract_end',
        'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'contract_end' => 'date',
        ];
    }

    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Students enrolled on a given day, from the enrollment history. */
    public function activeStudentCountOn(Carbon $date): int
    {
        return $this->enrollments()
            ->where('is_billable', true)
            ->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->distinct('student_id')
            ->count('student_id');
    }

    /** Invoiced less collected, across every issued invoice. */
    public function outstandingBalance(): float
    {
        return round((float) $this->invoices()
            ->whereNotIn('status', ['draft', 'void'])
            ->sum('balance_due'), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
