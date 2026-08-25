<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'contact_name', 'phone', 'email', 'address',
        'tax_number', 'payment_terms_days', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Unpaid supplier bills — the 2010 subsidiary ledger. */
    public function payableBalance(): float
    {
        return round((float) $this->expenses()
            ->where('status', 'posted')
            ->where('payment_status', 'unpaid')
            ->sum(\Illuminate\Support\Facades\DB::raw('total - amount_paid')), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
