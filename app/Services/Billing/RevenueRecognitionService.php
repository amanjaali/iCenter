<?php

namespace App\Services\Billing;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §4.4 — "If a bus company pays several months in advance, the
 * payment is recorded in 2060 Deferred revenue and released to 4010 Student
 * subscription revenue month by month."
 *
 * This is the release step: it finds invoices whose billed month has now
 * arrived and moves the credit from 2060 into 4010.
 */
class RevenueRecognitionService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Invoices sitting in deferred revenue whose month has arrived.
     *
     * @return Collection<int,Invoice>
     */
    public function due(?Carbon $asAt = null): Collection
    {
        $asAt = ($asAt ?? Carbon::today())->copy()->endOfMonth();

        return Invoice::with('busCompany')
            ->issued()
            ->where('is_deferred', true)
            ->where('revenue_recognised', false)
            ->whereDate('billing_month', '<=', $asAt->copy()->startOfMonth())
            ->orderBy('billing_month')
            ->get();
    }

    /** Release one invoice's deferred revenue into earned revenue. */
    public function recognise(Invoice $invoice, ?Carbon $date = null): Invoice
    {
        if ($invoice->revenue_recognised) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $date) {
            $invoice->loadMissing('busCompany');

            // Recognise in the month the service was delivered, unless that
            // month has been closed — then recognise on the run date.
            $recognitionDate = $date?->copy() ?? $this->defaultRecognitionDate($invoice);
            $project = Project::default();

            $journal = $this->journals->post(
                $recognitionDate,
                "Revenue recognition — {$invoice->number} — {$invoice->billing_month->format('F Y')}",
                [
                    [
                        'account' => Account::system('deferred_revenue'),
                        'description' => "Release deferred revenue — {$invoice->busCompany->name}",
                        'debit' => (float) $invoice->total,
                        'bus_company_id' => $invoice->bus_company_id,
                        'project_id' => $project?->id,
                    ],
                    [
                        'account' => Account::system('subscription_revenue'),
                        'description' => "Earned — {$invoice->billing_month->format('F Y')}",
                        'credit' => (float) $invoice->total,
                        'bus_company_id' => $invoice->bus_company_id,
                        'project_id' => $project?->id,
                    ],
                ],
                JournalSource::RevenueRecognition,
                $invoice,
            );

            $invoice->forceFill([
                'revenue_recognised' => true,
                'recognised_on' => $recognitionDate,
                'recognition_journal_id' => $journal->id,
            ])->save();

            $this->audit->record(
                'revenue_recognised',
                $invoice,
                newValues: ['amount' => (float) $invoice->total, 'journal' => $journal->reference],
                description: "Recognised revenue for invoice {$invoice->number}",
            );

            return $invoice->refresh();
        });
    }

    /**
     * Release every invoice that has come due.
     *
     * @return array{recognised: int, amount: float, failures: array<int,string>}
     */
    public function recogniseDue(?Carbon $asAt = null): array
    {
        $count = 0;
        $amount = 0.0;
        $failures = [];

        foreach ($this->due($asAt) as $invoice) {
            try {
                $this->recognise($invoice);
                $count++;
                $amount += (float) $invoice->total;
            } catch (\Throwable $e) {
                // One closed period must not stop the rest of the run.
                $failures[] = "{$invoice->number}: {$e->getMessage()}";
            }
        }

        return ['recognised' => $count, 'amount' => round($amount, 2), 'failures' => $failures];
    }

    private function defaultRecognitionDate(Invoice $invoice): Carbon
    {
        $serviceMonthEnd = $invoice->billing_month->copy()->endOfMonth();
        $period = \App\Models\Period::forDate($serviceMonthEnd);

        return $period && $period->isOpen() ? $serviceMonthEnd : Carbon::today();
    }
}
