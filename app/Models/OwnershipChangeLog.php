<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipChangeLog extends Model
{
    protected $fillable = [
        'partner_id', 'old_percent', 'new_percent', 'effective_date', 'reason', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'old_percent' => 'decimal:4',
            'new_percent' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
