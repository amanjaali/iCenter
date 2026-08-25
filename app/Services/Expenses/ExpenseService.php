<?php

namespace App\Services\Expenses;

use App\Enums\CapitalTreatment;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\SupplierPayment;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §7 — expense classification.
 *
 * Every expense carries an account, a cost centre and a project, and is marked
 * fixed or variable and capex or opex. Capital expenditure is capitalised into
 * a fixed asset rather than charged to the period.
 *
 * Posting:
 *   opex, paid now   DR expense account      CR cash/bank
 *   opex, on credit  DR expense account      CR 2010 Accounts payable
 *   capex            DR fixed asset account  CR cash/bank or 2010
 */
class ExpenseService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /** Create a draft expense. Nothing hits the ledger until it is posted. */
    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $date = Carbon::parse($data['expense_date']);
            $amount = Money::round($data['amount']);
            $tax = Money::round($data['tax_amount'] ?? 0);

            $expense = Expense::create([
                'number' => DocumentSequence::next('expense', (int) $date->year),
                'expense_date' => $date,
                'period_id' => $this->periods->resolveFor($date)->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'account_id' => $data['account_id'],
                'department_id' => $data['department_id'],
                'project_id' => $data['project_id'],
                'description' => $data['description'],
                'amount' => $amount,
                'tax_amount' => $tax,
                'total' => Money::round($amount + $tax),
                'nature' => $data['nature'] ?? 'variable',
                'treatment' => $data['treatment'] ?? 'opex',
                'payment_status' => $data['payment_status'] ?? 'paid',
                'paid_from_account_id' => $data['paid_from_account_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            return $expense->fresh(['account', 'department', 'project', 'supplier']);
        });
    }

    /** Post an expense to the ledger. */
    public function post(Expense $expense): Expense
    {
        if (! $expense->isDraft()) {
            throw new PostingException("Expense {$expense->number} has already been posted.");
        }

        $expense->loadMissing('account', 'department', 'project', 'supplier');

        if (Money::isZero((float) $expense->total)) {
            throw new PostingException("Expense {$expense->number} has no amount.");
        }

        // Paid now needs somewhere to pay from; on credit needs a supplier so
        // the payable can be chased.
        if ($expense->payment_status === 'paid' && ! $expense->paid_from_account_id) {
            throw new PostingException('Choose the cash or bank account this expense was paid from.');
        }

        return DB::transaction(function () use ($expense) {
            $creditAccount = $expense->payment_status === 'paid'
                ? Account::findOrFail($expense->paid_from_account_id)
                : Account::system('payable');

            $debitLine = [
                'account' => $expense->account,
                'description' => $expense->description,
                'debit' => (float) $expense->total,
                'department_id' => $expense->department_id,
                'project_id' => $expense->project_id,
                'supplier_id' => $expense->supplier_id,
                'nature' => $expense->nature,
                'treatment' => $expense->treatment,
            ];

            $journal = $this->journals->post(
                $expense->expense_date,
                "Expense {$expense->number} — {$expense->description}",
                [
                    $debitLine,
                    [
                        'account' => $creditAccount,
                        'description' => $expense->payment_status === 'paid'
                            ? "Paid — {$expense->description}"
                            : 'Payable to '.($expense->supplier?->name ?? 'supplier'),
                        'credit' => (float) $expense->total,
                        'supplier_id' => $expense->supplier_id,
                        'department_id' => $expense->department_id,
                        'project_id' => $expense->project_id,
                    ],
                ],
                JournalSource::Expense,
                $expense,
            );

            $expense->forceFill([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'amount_paid' => $expense->payment_status === 'paid' ? (float) $expense->total : 0,
                'posted_by' => Auth::id(),
            ])->save();

            $this->audit->record(
                'expense_posted',
                $expense,
                newValues: [
                    'account' => $expense->account->code,
                    'total' => (float) $expense->total,
                    'journal' => $journal->reference,
                ],
                description: "Posted expense {$expense->number}",
            );

            return $expense->refresh();
        });
    }

    /**
     * Capitalise a posted capex expense into a fixed asset that will then be
     * depreciated. The cost is already sitting in the asset account; this
     * creates the register entry that drives depreciation.
     */
    public function capitalise(Expense $expense, array $data): FixedAsset
    {
        if (! $expense->isCapex()) {
            throw new PostingException("Expense {$expense->number} is not capital expenditure.");
        }

        if (! $expense->isPosted()) {
            throw new PostingException("Post expense {$expense->number} before capitalising it.");
        }

        if (FixedAsset::where('expense_id', $expense->id)->exists()) {
            throw new PostingException("Expense {$expense->number} has already been capitalised.");
        }

        return DB::transaction(function () use ($expense, $data) {
            $asset = FixedAsset::create([
                'code' => $data['code'] ?? 'FA-'.str_pad((string) (FixedAsset::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'name' => $data['name'] ?? $expense->description,
                'category' => $data['category'],
                'asset_account_id' => $expense->account_id,
                'accumulated_depreciation_account_id' => Account::system('accumulated_depreciation')->id,
                'depreciation_expense_account_id' => $data['depreciation_expense_account_id']
                    ?? Account::system($data['category'] === 'vehicle' ? 'vehicle_depreciation' : 'depreciation_other')->id,
                'department_id' => $expense->department_id,
                'project_id' => $expense->project_id,
                'expense_id' => $expense->id,
                'acquisition_date' => $expense->expense_date,
                'depreciation_start_date' => $data['depreciation_start_date'] ?? $expense->expense_date,
                'cost' => (float) $expense->total,
                'salvage_value' => Money::round($data['salvage_value'] ?? 0),
                'useful_life_months' => (int) $data['useful_life_months'],
                'serial_number' => $data['serial_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->audit->record(
                'asset_capitalised',
                $asset,
                newValues: ['cost' => (float) $asset->cost, 'from_expense' => $expense->number],
                description: "Capitalised {$asset->name} from expense {$expense->number}",
            );

            return $asset;
        });
    }

    /** Settle an unpaid supplier bill. */
    public function paySupplier(
        Expense $expense,
        Carbon $date,
        float $amount,
        Account $sourceAccount,
        string $method = 'bank',
        ?string $reference = null,
    ): SupplierPayment {
        $amount = Money::round($amount);

        if (! $expense->isPosted()) {
            throw new PostingException("Expense {$expense->number} has not been posted.");
        }

        if ($amount <= 0 || $amount > $expense->balanceDue() + 0.005) {
            throw new PostingException(
                'The payment must be between zero and the outstanding '.Money::format($expense->balanceDue()).'.'
            );
        }

        return DB::transaction(function () use ($expense, $date, $amount, $sourceAccount, $method, $reference) {
            $payment = SupplierPayment::create([
                'reference' => DocumentSequence::next('payment', (int) $date->year),
                'expense_id' => $expense->id,
                'supplier_id' => $expense->supplier_id,
                'payment_date' => $date,
                'amount' => $amount,
                'method' => $method,
                'source_account_id' => $sourceAccount->id,
                'reference_note' => $reference,
                'created_by' => Auth::id(),
            ]);

            $journal = $this->journals->post(
                $date,
                "Supplier payment — {$expense->number}",
                [
                    [
                        'account' => Account::system('payable'),
                        'description' => 'Settlement — '.($expense->supplier?->name ?? $expense->description),
                        'debit' => $amount,
                        'supplier_id' => $expense->supplier_id,
                    ],
                    [
                        'account' => $sourceAccount,
                        'description' => "Paid — {$expense->description}".($reference ? " (ref {$reference})" : ''),
                        'credit' => $amount,
                        'supplier_id' => $expense->supplier_id,
                    ],
                ],
                JournalSource::SupplierPayment,
                $payment,
            );

            $payment->update(['journal_id' => $journal->id]);
            $expense->refreshPaymentState();

            return $payment->refresh();
        });
    }

    /** Reverse a posted expense — deletion is not permitted (§9.3). */
    public function void(Expense $expense, string $reason): Expense
    {
        if ($expense->status === 'void') {
            return $expense;
        }

        if ((float) $expense->amount_paid > 0 && $expense->payment_status === 'unpaid') {
            throw new PostingException('Reverse the supplier payments before voiding this expense.');
        }

        return DB::transaction(function () use ($expense, $reason) {
            if ($expense->journal && $expense->journal->canBeReversed()) {
                $this->journals->reverse($expense->journal, "Expense {$expense->number} voided — {$reason}");
            }

            $expense->forceFill([
                'status' => 'void',
                'notes' => trim(($expense->notes ? $expense->notes."\n" : '')."Voided: {$reason}"),
            ])->save();

            $this->audit->record(
                'expense_voided',
                $expense,
                description: "Voided expense {$expense->number}",
                reason: $reason,
            );

            return $expense->refresh();
        });
    }

    public function deleteDraft(Expense $expense): void
    {
        if (! $expense->isDraft()) {
            throw new PostingException(
                "Expense {$expense->number} is {$expense->status} and cannot be deleted. Void it instead."
            );
        }

        $number = $expense->number;
        $expense->delete();

        $this->audit->record('deleted', null, description: "Deleted draft expense {$number}");
    }
}
