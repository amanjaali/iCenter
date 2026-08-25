<?php

namespace App\Services\Billing;

use App\Enums\DocumentStatus;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Issuing, posting and voiding invoices.
 *
 * Scope of work §4.4 sets the revenue recognition rule this implements:
 *   - a month already served    → DR 1030 receivable / CR 4010 revenue
 *   - a month billed in advance → DR 1030 receivable / CR 2060 deferred
 *     revenue, released to 4010 when that month arrives.
 */
class InvoiceService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Issue a draft invoice: post it to the ledger and make it collectable.
     */
    public function issue(Invoice $invoice): Invoice
    {
        if (! $invoice->isDraft()) {
            throw new PostingException("Invoice {$invoice->number} has already been issued.");
        }

        if (Money::isZero((float) $invoice->total)) {
            throw new PostingException("Invoice {$invoice->number} has no amount to bill.");
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->loadMissing('busCompany', 'lines');

            $project = Project::default();
            $company = $invoice->busCompany;

            // A month billed before it has been served is deferred; the credit
            // sits in 2060 until the month arrives.
            $deferred = $this->isDeferred($invoice);
            $creditAccount = $deferred
                ? Account::system('deferred_revenue')
                : Account::system('subscription_revenue');

            $journal = $this->journals->post(
                $invoice->issue_date,
                "Invoice {$invoice->number} — {$company->name} — {$invoice->billing_month->format('F Y')}",
                [
                    [
                        'account' => Account::system('receivable'),
                        'description' => "{$company->name} — {$invoice->billing_month->format('M Y')} subscriptions",
                        'debit' => (float) $invoice->total,
                        'bus_company_id' => $company->id,
                        'project_id' => $project?->id,
                    ],
                    [
                        'account' => $creditAccount,
                        'description' => $invoice->student_count.' students × '
                            .Money::format($invoice->lines->first()?->unit_price ?? 0).' — '
                            .$invoice->billing_month->format('M Y'),
                        'credit' => (float) $invoice->total,
                        'bus_company_id' => $company->id,
                        'project_id' => $project?->id,
                    ],
                ],
                JournalSource::Invoice,
                $invoice,
            );

            $invoice->forceFill([
                'status' => DocumentStatus::Issued,
                'journal_id' => $journal->id,
                'is_deferred' => $deferred,
                'revenue_recognised' => ! $deferred,
                'recognised_on' => $deferred ? null : $invoice->issue_date,
                'balance_due' => Money::round((float) $invoice->total - (float) $invoice->amount_paid),
                'issued_by' => Auth::id(),
            ])->save();

            $this->audit->record(
                'invoice_issued',
                $invoice,
                newValues: ['total' => (float) $invoice->total, 'journal' => $journal->reference],
                description: "Issued invoice {$invoice->number}",
            );

            return $invoice->refresh();
        });
    }

    /**
     * Void an invoice. A draft is simply marked void; an issued one has its
     * journal reversed, because §9.3 forbids deleting a posted entry.
     */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->isVoid()) {
            return $invoice;
        }

        if ((float) $invoice->amount_paid > 0) {
            throw new PostingException(
                "Invoice {$invoice->number} has payments against it. Reverse the payments before voiding it."
            );
        }

        return DB::transaction(function () use ($invoice, $reason) {
            foreach ([$invoice->recognitionJournal, $invoice->journal] as $journal) {
                if ($journal && $journal->canBeReversed()) {
                    $this->journals->reverse($journal, "Invoice {$invoice->number} voided — {$reason}");
                }
            }

            $invoice->forceFill([
                'status' => DocumentStatus::Void,
                'balance_due' => 0,
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '')."Voided: {$reason}"),
            ])->save();

            $this->audit->record(
                'invoice_voided',
                $invoice,
                description: "Voided invoice {$invoice->number}",
                reason: $reason,
            );

            return $invoice->refresh();
        });
    }

    /** Delete a draft invoice that was generated in error. */
    public function deleteDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw new PostingException(
                "Invoice {$invoice->number} is {$invoice->status->value} and cannot be deleted. Void it instead."
            );
        }

        $number = $invoice->number;
        $invoice->delete();

        $this->audit->record('deleted', null, description: "Deleted draft invoice {$number}");
    }

    /** Apply a discount or adjustment (4019) to a draft invoice. */
    public function applyDiscount(Invoice $invoice, float $amount, string $note): Invoice
    {
        if (! $invoice->isDraft()) {
            throw new PostingException('A discount can only be applied while the invoice is still a draft.');
        }

        $amount = Money::round($amount);

        if ($amount < 0 || $amount > (float) $invoice->subtotal) {
            throw new PostingException('The discount must be between zero and the invoice subtotal.');
        }

        $total = Money::round((float) $invoice->subtotal - $amount);

        $invoice->forceFill([
            'discount_amount' => $amount,
            'total' => $total,
            'balance_due' => $total,
            'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '')."Discount: {$note}"),
        ])->save();

        return $invoice->refresh();
    }

    private function isDeferred(Invoice $invoice): bool
    {
        // Revenue is earned in the month the service is delivered (§4.4), so a
        // month that has not started yet on the issue date is deferred.
        return $invoice->billing_month->copy()->startOfMonth()
            ->greaterThan($invoice->issue_date->copy()->startOfMonth());
    }
}
