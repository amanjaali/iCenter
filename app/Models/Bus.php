<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bus extends Model
{
    use Auditable;

    protected $fillable = [
        'bus_company_id', 'code', 'plate_number', 'capacity', 'driver_name',
        'driver_phone', 'route_name', 'device_serial', 'status', 'notes',
    ];

    public function busCompany(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function label(): string
    {
        return $this->code.($this->route_name ? ' · '.$this->route_name : '');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
