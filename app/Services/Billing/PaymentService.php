<?php

namespace App\Services\Billing;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\BusCompany;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §4.3 — "The system must track invoiced, collected, and
 * outstanding amounts — per bus company, per month."
 *
 * A receipt debits cash or bank and credits receivables. Cash received beyond
 * what is currently invoiced stays unallocated on the payment and can be
 * applied to later invoices as they are raised.
 */
class PaymentService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record and post a receipt.
     *
     * @param  array<int,array{invoice_id:int, amount:float}>  $allocations
     */
    public function record(
        BusCompany $company,
        Carbon $date,
        float $amount,
        Account $depositAccount,
        array $allocations = [],
        string $method = 'bank',
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        $amount = Money::round($amount);

        if ($amount <= 0) {
            throw new PostingException('A receipt must be for a positive amount.');
        }

        return DB::transaction(function () use ($company, $date, $amount, $depositAccount, $allocations, $method, $reference, $notes) {
            $period = $this->periods->resolveFor($date);

            $payment = Payment::create([
                'number' => DocumentSequence::next('payment', (int) $date->year),
                'bus_company_id' => $company->id,
                'period_id' => $period->id,
                'payment_date' => $date,
                'amount' => $amount,
                'allocated_amount' => 0,
                'unallocated_amount' => $amount,
                'method' => $method,
                'deposit_account_id' => $depositAccount->id,
                'reference' => $reference,
                'status' => 'draft',
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            $this->applyAllocations($payment, $allocations);

            $journal = $this->journals->post(
                $date,
                "Receipt {$payment->number} — {$company->name}",
                [
                    [
                        'account' => $depositAccount,
                        'description' => "Received from {$company->name}"
                            .($reference ? " (ref {$reference})" : ''),
                        'debit' => $amount,
                        'bus_company_id' => $company->id,
                    ],
                    [
                        'account' => Account::system('receivable'),
                        'description' => "Settlement of receivables — {$company->name}",
                        'credit' => $amount,
                        'bus_company_id' => $company->id,
                        'project_id' => Project::default()?->id,
                    ],
                ],
                JournalSource::Payment,
                $payment,
            );

            $payment->forceFill(['status' => 'posted', 'journal_id' => $journal->id])->save();

            // Only now that the payment is posted do the invoices count it.
            $this->refreshInvoices($payment);

            $this->audit->record(
                'payment_recorded',
                $payment,
                newValues: ['amount' => $amount, 'journal' => $journal->reference],
                description: "Recorded receipt {$payment->number} from {$company->name}",
            );

            return $payment->refresh(['allocations.invoice']);
        });
    }

    /**
     * Apply unallocated cash on an existing posted payment to further invoices.
     * This is how an advance payment is consumed month by month.
     *
     * @param  array<int,array{invoice_id:int, amount:float}>  $allocations
     */
    public function allocate(Payment $payment, array $allocations): Payment
    {
        if (! $payment->isPosted()) {
            throw new PostingException("Payment {$payment->number} is not posted.");
        }

        return DB::transaction(function () use ($payment, $allocations) {
            $this->applyAllocations($payment, $allocations);
            $this->refreshInvoices($payment);

            $this->audit->record(
                'payment_allocated',
                $payment,
                newValues: ['allocated' => (float) $payment->fresh()->allocated_amount],
                description: "Allocated receipt {$payment->number}",
            );

            return $payment->refresh(['allocations.invoice']);
        });
    }

    /**
     * Suggest an allocation across a company's outstanding invoices, oldest
     * first. The accountant can override every figure before saving.
     *
     * @return array<int,array{invoice:Invoice, amount:float}>
     */
    public function suggestAllocation(BusCompany $company, float $amount): array
    {
        $remaining = Money::round($amount);
        $suggestions = [];

        $invoices = Invoice::where('bus_company_id', $company->id)
            ->outstanding()
            ->orderBy('due_date')
            ->orderBy('billing_month')
            ->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, (float) $invoice->balance_due);

            if ($apply <= 0) {
                continue;
            }

            $suggestions[] = ['invoice' => $invoice, 'amount' => Money::round($apply)];
            $remaining = Money::round($remaining - $apply);
        }

        return $suggestions;
    }

    /** Reverse a posted receipt — §9.3 forbids deleting it. */
    public function void(Payment $payment, string $reason): Payment
    {
        if ($payment->status === 'void') {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $reason) {
            if ($payment->journal && $payment->journal->canBeReversed()) {
                $this->journals->reverse($payment->journal, "Receipt {$payment->number} voided — {$reason}");
            }

            $invoiceIds = $payment->allocations()->pluck('invoice_id')->all();
            $payment->allocations()->delete();

            $payment->forceFill([
                'status' => 'void',
                'allocated_amount' => 0,
                'unallocated_amount' => 0,
                'notes' => trim(($payment->notes ? $payment->notes."\n" : '')."Voided: {$reason}"),
            ])->save();

            Invoice::whereIn('id', $invoiceIds)->get()->each->refreshPaymentState();

            $this->audit->record(
                'payment_voided',
                $payment,
                description: "Voided receipt {$payment->number}",
                reason: $reason,
            );

            return $payment->refresh();
        });
    }

    /**
     * Write off an uncollectable receivable to 9080 Bad debt written off.
     */
    public function writeOff(Invoice $invoice, string $reason, ?Carbon $date = null): Invoice
    {
        $balance = (float) $invoice->balance_due;

        if ($balance <= 0) {
            throw new PostingException("Invoice {$invoice->number} has nothing outstanding to write off.");
        }

        return DB::transaction(function () use ($invoice, $reason, $date, $balance) {
            $invoice->loadMissing('busCompany');
            $writeOffDate = $date ?? Carbon::today();

            $journal = $this->journals->post(
                $writeOffDate,
                "Bad debt write-off — invoice {$invoice->number}",
                [
                    [
                        'account' => Account::system('bad_debt'),
                        'description' => "Write-off — {$invoice->busCompany->name} — {$reason}",
                        'debit' => $balance,
                        'bus_company_id' => $invoice->bus_company_id,
                        'project_id' => Project::default()?->id,
                        'department_id' => \App\Models\Department::where('code', 'ADM')->value('id'),
                    ],
                    [
                        'account' => Account::system('receivable'),
                        'description' => "Write-off of invoice {$invoice->number}",
                        'credit' => $balance,
                        'bus_company_id' => $invoice->bus_company_id,
                    ],
                ],
                JournalSource::Manual,
                $invoice,
            );

            $invoice->forceFill([
                'balance_due' => 0,
                'status' => \App\Enums\DocumentStatus::Paid,
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '')."Written off: {$reason}"),
            ])->save();

            $this->audit->record(
                'invoice_written_off',
                $invoice,
                newValues: ['amount' => $balance, 'journal' => $journal->reference],
                description: "Wrote off invoice {$invoice->number}",
                reason: $reason,
            );

            return $invoice->refresh();
        });
    }

    /**
     * @param  array<int,array{invoice_id:int, amount:float}>  $allocations
     */
    private function applyAllocations(Payment $payment, array $allocations): void
    {
        $totalAllocated = (float) $payment->allocations()->sum('amount');

        foreach ($allocations as $allocation) {
            $amount = Money::round($allocation['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $invoice = Invoice::lockForUpdate()->findOrFail($allocation['invoice_id']);

            if ($invoice->bus_company_id !== $payment->bus_company_id) {
                throw new PostingException(
                    "Invoice {$invoice->number} belongs to a different bus company and cannot be settled by this receipt."
                );
            }

            $alreadyOnThisInvoice = (float) PaymentAllocation::where('payment_id', $payment->id)
                ->where('invoice_id', $invoice->id)
                ->value('amount') ?? 0.0;

            $invoiceRemaining = Money::round((float) $invoice->balance_due + $alreadyOnThisInvoice);

            if ($amount > $invoiceRemaining + 0.005) {
                throw new PostingException(
                    'Cannot apply '.Money::exact($amount)." to invoice {$invoice->number}; only "
                    .Money::exact($invoiceRemaining).' is outstanding.'
                );
            }

            $paymentRemaining = Money::round((float) $payment->amount - $totalAllocated + $alreadyOnThisInvoice);

            if ($amount > $paymentRemaining + 0.005) {
                throw new PostingException(
                    'The allocation exceeds the receipt: only '.Money::exact($paymentRemaining).' is left to apply.'
                );
            }

            PaymentAllocation::updateOrCreate(
                ['payment_id' => $payment->id, 'invoice_id' => $invoice->id],
                ['amount' => $amount],
            );

            $totalAllocated = Money::round($totalAllocated - $alreadyOnThisInvoice + $amount);
        }

        $payment->forceFill([
            'allocated_amount' => $totalAllocated,
            'unallocated_amount' => Money::round((float) $payment->amount - $totalAllocated),
        ])->save();
    }

    private function refreshInvoices(Payment $payment): void
    {
        $payment->allocations()->with('invoice')->get()
            ->each(fn (PaymentAllocation $a) => $a->invoice?->refreshPaymentState());
    }
}
