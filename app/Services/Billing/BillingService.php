<?php

namespace App\Services\Billing;

use App\Enums\DocumentStatus;
use App\Models\AcademicYear;
use App\Models\BusCompany;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceLineStudent;
use App\Models\StudentEnrollment;
use App\Services\Accounting\PeriodService;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §4.3 — the monthly billing cycle.
 *
 * "Billing must be generated from the registry, not entered manually." The
 * chain is bus company → buses → enrolled students → days present → amount.
 * Nothing here takes a typed-in quantity.
 */
class BillingService
{
    public function __construct(
        private readonly RateResolver $rates,
        private readonly SettingsService $settings,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Preview what a company would be billed for a month, without writing
     * anything. The generate screen shows this before the accountant commits.
     *
     * @return array{
     *   billable: bool, reason: ?string, student_count: int, billable_units: float,
     *   rate: float, total: float, lines: array<int,array<string,mixed>>
     * }
     */
    public function preview(BusCompany $company, Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $daysInMonth = (int) $month->daysInMonth;

        $academicYear = AcademicYear::forDate($month);

        if (! $academicYear) {
            return $this->notBillable('No academic year covers '.$month->format('F Y').'.');
        }

        // §10.3 — the holiday and summer rule, set per academic year.
        if (! $academicYear->isBillableMonth($month)) {
            return $this->notBillable(
                $month->format('F Y').' is outside the billing months of '.$academicYear->name.'.'
            );
        }

        $rate = $this->rates->resolve($company, $monthEnd);

        if (! $rate) {
            return $this->notBillable('No rate is in force for '.$company->name.' in '.$month->format('F Y').'.');
        }

        $method = $this->settings->prorationMethod();
        $unitPrice = (float) $rate->amount;

        // One line per bus, so the bus company statement can show students per
        // bus. Enrollments with no bus fall into an "unassigned" line.
        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('bus_company_id', $company->id)
            ->billable()
            ->overlapping($month, $monthEnd)
            ->get();

        $lines = [];
        $seenStudents = [];

        foreach ($enrollments->groupBy('bus_id') as $busId => $group) {
            $students = [];

            foreach ($group as $enrollment) {
                $days = $enrollment->billableDaysIn($month, $monthEnd);

                if ($days <= 0) {
                    continue;
                }

                $units = $method->units($days, $daysInMonth);

                if ($units <= 0) {
                    continue;
                }

                $students[] = [
                    'student_id' => $enrollment->student_id,
                    'student_name' => $enrollment->student?->name ?? '—',
                    'student_code' => $enrollment->student?->code ?? '—',
                    'enrollment_id' => $enrollment->id,
                    'billable_days' => $days,
                    'days_in_month' => $daysInMonth,
                    'billable_units' => $units,
                    // Provisional: reconciled against the whole-dinar line
                    // total below so the parts always add up to the whole.
                    'amount' => Money::round($units * $unitPrice),
                    'is_partial_month' => $days < $daysInMonth,
                    'note' => $this->partialNote($enrollment, $month, $monthEnd),
                ];

                $seenStudents[$enrollment->student_id] = true;
            }

            if ($students === []) {
                continue;
            }

            $bus = $group->first()->bus;

            // The line is billed in whole dinars, then that exact figure is
            // shared back over the students by largest remainder. Without this
            // the per-student detail would drift a dinar away from the line it
            // belongs to, and the invoice a dinar away from its own lines.
            $lineTotal = Money::whole(array_sum(array_column($students, 'amount')));

            $shares = Money::allocate(
                $lineTotal,
                array_map(fn (array $s) => (float) $s['billable_units'], $students),
            );

            foreach ($students as $index => $student) {
                $students[$index]['amount'] = Money::whole($shares[$index] ?? 0);
            }

            $lines[] = [
                'bus_id' => $busId ?: null,
                'bus_label' => $bus?->label() ?? 'Unassigned students',
                'rate_card_id' => $rate->id,
                'student_count' => count($students),
                'billable_units' => round(array_sum(array_column($students, 'billable_units')), 4),
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'students' => $students,
            ];
        }

        if ($lines === []) {
            return $this->notBillable('No billable students were enrolled with '.$company->name.' in '.$month->format('F Y').'.');
        }

        usort($lines, fn ($a, $b) => strcmp((string) $a['bus_label'], (string) $b['bus_label']));

        return [
            'billable' => true,
            'reason' => null,
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->name,
            'rate_card_id' => $rate->id,
            'rate' => $unitPrice,
            'proration_method' => $method,
            'student_count' => count($seenStudents),
            'billable_units' => round(array_sum(array_column($lines, 'billable_units')), 4),
            'total' => Money::whole(array_sum(array_column($lines, 'line_total'))),
            'lines' => $lines,
        ];
    }

    /**
     * Create the draft invoice for one company and month.
     *
     * Returns null when the month is not billable for that company, so callers
     * can run the whole book and report skips rather than failing the batch.
     */
    public function generateFor(BusCompany $company, Carbon $month, ?Carbon $issueDate = null): ?Invoice
    {
        $month = $month->copy()->startOfMonth();

        // §4.3 — one invoice per company per month. A month that is already
        // billed is skipped rather than duplicated; the unique index is the
        // backstop if two operators run generation at once.
        if (Invoice::where('bus_company_id', $company->id)->forMonth($month)->exists()) {
            return null;
        }

        $preview = $this->preview($company, $month);

        if (! $preview['billable']) {
            return null;
        }

        return DB::transaction(function () use ($company, $month, $issueDate, $preview) {
            $issue = $issueDate?->copy() ?? $month->copy()->endOfMonth();
            $due = $issue->copy()->addDays($company->payment_terms_days ?: $this->settings->invoiceDueDays());
            $period = $this->periods->resolveFor($issue);

            $invoice = Invoice::create([
                'number' => DocumentSequence::next('invoice', (int) $issue->year),
                'bus_company_id' => $company->id,
                'academic_year_id' => $preview['academic_year_id'],
                'period_id' => $period->id,
                'billing_month' => $month,
                'issue_date' => $issue,
                'due_date' => $due,
                'subtotal' => $preview['total'],
                'discount_amount' => 0,
                'total' => $preview['total'],
                'amount_paid' => 0,
                'balance_due' => $preview['total'],
                'student_count' => $preview['student_count'],
                'billable_units' => $preview['billable_units'],
                // The rule in force is frozen onto the invoice so a historical
                // invoice can always be reproduced, even after the setting
                // changes.
                'proration_method' => $preview['proration_method'],
                'status' => DocumentStatus::Draft,
                // §4.4 — a month billed before it is served is deferred revenue.
                'is_deferred' => $month->greaterThan(Carbon::today()->startOfMonth()),
                'created_by' => Auth::id(),
            ]);

            foreach (array_values($preview['lines']) as $index => $line) {
                $invoiceLine = InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'bus_id' => $line['bus_id'],
                    'rate_card_id' => $line['rate_card_id'],
                    'description' => $line['bus_label'].' — '.$month->format('F Y'),
                    'student_count' => $line['student_count'],
                    'billable_units' => $line['billable_units'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'line_no' => $index + 1,
                ]);

                // Inserted in bulk: a large operator can have thousands of
                // students on one invoice, and a row-at-a-time insert would
                // make month-end generation crawl.
                $detail = array_map(fn (array $student) => [
                    'invoice_line_id' => $invoiceLine->id,
                    'student_id' => $student['student_id'],
                    'student_enrollment_id' => $student['enrollment_id'],
                    'billable_days' => $student['billable_days'],
                    'days_in_month' => $student['days_in_month'],
                    'billable_units' => $student['billable_units'],
                    'amount' => $student['amount'],
                    'is_partial_month' => $student['is_partial_month'],
                    'note' => $student['note'],
                ], $line['students']);

                foreach (array_chunk($detail, 500) as $chunk) {
                    InvoiceLineStudent::insert($chunk);
                }
            }

            $this->audit->record(
                'invoice_generated',
                $invoice,
                newValues: [
                    'month' => $month->format('Y-m'),
                    'students' => $preview['student_count'],
                    'total' => $preview['total'],
                ],
                description: "Generated invoice {$invoice->number} for {$company->name} — {$month->format('F Y')}",
            );

            return $invoice->fresh(['lines.students', 'busCompany']);
        });
    }

    /**
     * Run generation across every active bus company for a month.
     *
     * @return array{created: Collection<int,Invoice>, skipped: array<int,array{company:string, reason:string}>}
     */
    public function generateMonth(Carbon $month, ?array $companyIds = null, ?Carbon $issueDate = null): array
    {
        $month = $month->copy()->startOfMonth();

        $companies = BusCompany::active()
            ->when($companyIds, fn ($q) => $q->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();

        $created = collect();
        $skipped = [];

        foreach ($companies as $company) {
            if (Invoice::where('bus_company_id', $company->id)->forMonth($month)->exists()) {
                $skipped[] = ['company' => $company->name, 'reason' => 'Already invoiced for this month.'];

                continue;
            }

            $preview = $this->preview($company, $month);

            if (! $preview['billable']) {
                $skipped[] = ['company' => $company->name, 'reason' => $preview['reason']];

                continue;
            }

            if ($invoice = $this->generateFor($company, $month, $issueDate)) {
                $created->push($invoice);
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Which companies still have no invoice for a month. Drives the "needs
     * attention" panel on the dashboard, so a missed company is visible rather
     * than simply absent.
     *
     * @return Collection<int,BusCompany>
     */
    public function companiesMissingInvoice(Carbon $month): Collection
    {
        $month = $month->copy()->startOfMonth();

        $invoiced = Invoice::forMonth($month)->pluck('bus_company_id')->all();

        return BusCompany::active()
            ->whereNotIn('id', $invoiced ?: [0])
            ->orderBy('name')
            ->get()
            ->filter(fn (BusCompany $c) => $this->preview($c, $month)['billable'])
            ->values();
    }

    /** A short human note explaining why a student was billed part of a month. */
    private function partialNote(StudentEnrollment $enrollment, Carbon $monthStart, Carbon $monthEnd): ?string
    {
        $notes = [];

        if ($enrollment->start_date->greaterThan($monthStart)) {
            $notes[] = 'joined '.$enrollment->start_date->format('j M');
        }

        if ($enrollment->end_date && $enrollment->end_date->lessThan($monthEnd)) {
            $notes[] = 'left '.$enrollment->end_date->format('j M');
        }

        return $notes === [] ? null : ucfirst(implode(', ', $notes));
    }

    private function notBillable(string $reason): array
    {
        return [
            'billable' => false,
            'reason' => $reason,
            'academic_year_id' => null,
            'academic_year' => null,
            'rate_card_id' => null,
            'rate' => 0.0,
            'proration_method' => $this->settings->prorationMethod(),
            'student_count' => 0,
            'billable_units' => 0.0,
            'total' => 0.0,
            'lines' => [],
        ];
    }
}
