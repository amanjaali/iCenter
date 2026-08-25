<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationLine extends Model
{
    protected $fillable = [
        'depreciation_run_id', 'fixed_asset_id', 'amount',
        'accumulated_after', 'net_book_value_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'accumulated_after' => 'decimal:2',
            'net_book_value_after' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DepreciationRun::class, 'depreciation_run_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
