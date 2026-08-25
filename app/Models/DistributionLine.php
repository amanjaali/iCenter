<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionLine extends Model
{
    protected $fillable = [
        'distribution_run_id', 'partner_id', 'ownership_percent',
        'share_amount', 'paid_amount', 'outstanding_amount',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percent' => 'decimal:4',
            'share_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DistributionRun::class, 'distribution_run_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class);
    }

    /** Recalculate paid and outstanding from the payouts recorded against it. */
    public function refreshPayoutState(): void
    {
        $paid = round((float) $this->payouts()->sum('amount'), 2);

        $this->forceFill([
            'paid_amount' => $paid,
            'outstanding_amount' => round((float) $this->share_amount - $paid, 2),
        ])->save();
    }
}
