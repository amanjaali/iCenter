<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scope of work §4.2 — "Any rate change must be logged with the user who made
 * it, the date, and the reason."
 */
class RateChangeLog extends Model
{
    protected $fillable = [
        'rate_card_id', 'user_id', 'user_name', 'action',
        'old_amount', 'new_amount', 'old_values', 'new_values', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
