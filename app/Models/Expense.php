<?php

namespace App\Models;

use App\Enums\CapitalTreatment;
use App\Enums\ExpenseNature;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope of work §7 — every expense carries an account, a cost centre and a
 * project, plus fixed/variable and capex/opex.
 */
class Expense extends Model
{
    use Auditable;

    protected $fillable = [
        'number', 'expense_date', 'period_id', 'supplier_id', 'account_id',
        'department_id', 'project_id', 'description', 'amount', 'tax_amount', 'total',
        'nature', 'treatment', 'payment_status', 'paid_from_account_id', 'due_date',
        'amount_paid', 'status', 'journal_id', 'attachment_path', 'reference',
        'notes', 'created_by', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'nature' => ExpenseNature::class,
            'treatment' => CapitalTreatment::class,
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function fixedAsset(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isCapex(): bool
    {
        return $this->treatment === CapitalTreatment::Capex;
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function refreshPaymentState(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);

        $this->forceFill([
            'amount_paid' => $paid,
            'payment_status' => $paid >= (float) $this->total - 0.005 ? 'paid' : 'unpaid',
        ])->save();
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }
}
