<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPayout extends Model
{
    use Auditable;

    protected $fillable = [
        'reference', 'distribution_line_id', 'partner_id', 'payout_date', 'amount',
        'method', 'source_account_id', 'reference_note', 'journal_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['payout_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(DistributionLine::class, 'distribution_line_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
