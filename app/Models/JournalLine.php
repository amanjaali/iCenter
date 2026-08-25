<?php

namespace App\Models;

use App\Enums\CapitalTreatment;
use App\Enums\ExpenseNature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_id', 'line_no', 'account_id', 'description', 'debit', 'credit',
        'department_id', 'project_id', 'partner_id', 'bus_company_id',
        'supplier_id', 'employee_id', 'nature', 'treatment',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'nature' => ExpenseNature::class,
            'treatment' => CapitalTreatment::class,
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function busCompany(): BelongsTo
    {
        return $this->belongsTo(BusCompany::class);
    }

    /** Movement in the account's own direction: + increases it. */
    public function signedAmount(): float
    {
        return $this->account->signedBalance((float) $this->debit, (float) $this->credit);
    }

    /** Restrict to lines belonging to journals that have actually been posted. */
    public function scopePosted(Builder $query): Builder
    {
        return $query->whereHas('journal', fn (Builder $j) => $j->posted());
    }
}
