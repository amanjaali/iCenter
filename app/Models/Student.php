<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use Auditable;

    protected $fillable = [
        'bus_company_id', 'bus_id', 'code', 'name', 'name_ar', 'guardian_name',
        'guardian_phone', 'school_name', 'grade', 'ble_tag', 'joined_on',
        'left_on', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function busCompany(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->orderBy('start_date');
    }

    /** Every invoice line this student has ever appeared on. */
    public function invoiceDetails(): HasMany
    {
        return $this->hasMany(InvoiceLineStudent::class);
    }

    public function currentEnrollment(): ?StudentEnrollment
    {
        return $this->enrollments()->whereNull('end_date')->latest('start_date')->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
