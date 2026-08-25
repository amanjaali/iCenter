<?php

namespace Tests\Feature;

use App\Enums\BillingMode;
use App\Enums\DocumentStatus;
use App\Enums\ProrationMethod;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bus;
use App\Models\BusCompany;
use App\Models\Invoice;
use App\Models\RateCard;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Billing\BillingService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\RateResolver;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Scope of work §3.1 and §4 — billing generated from the registry, at the rate
 * in force, with the mid-month rule applied consistently.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private BusCompany $company;

    private Bus $bus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installChartOfAccounts();

        $this->company = BusCompany::create([
            'code' => 'BC-T1',
            'name' => 'Test Transport',
            'payment_terms_days' => 15,
            'status' => 'active',
        ]);

        $this->bus = Bus::create([
            'bus_company_id' => $this->company->id,
            'code' => 'BUS-01',
            'capacity' => 30,
            'status' => 'active',
        ]);
    }

    /** Enrol a student for a date range and return them. */
    private function enrol(string $code, string $start, ?string $end = null): Student
    {
        $student = Student::create([
            'bus_company_id' => $this->company->id,
            'bus_id' => $this->bus->id,
            'code' => $code,
            'name' => 'Student '.$code,
            'joined_on' => $start,
            'left_on' => $end,
            'status' => $end ? 'left' : 'active',
        ]);

        StudentEnrollment::create([
            'student_id' => $student->id,
            'bus_id' => $this->bus->id,
            'bus_company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
            'is_billable' => true,
        ]);

        return $student;
    }

    #[Test]
    public function a_full_month_of_students_bills_at_the_standard_rate(): void
    {
        // Scope of work §4.1 — 4,000 IQD per student per month.
        $this->enrol('S1', '2026-09-01');
        $this->enrol('S2', '2026-09-01');
        $this->enrol('S3', '2026-09-01');

        $preview = app(BillingService::class)->preview($this->company, Carbon::create(2026, 9, 1));

        $this->assertTrue($preview['billable']);
        $this->assertSame(3, $preview['student_count']);
        $this->assertEqualsWithDelta(4000.0, $preview['rate'], 0.001);
        $this->assertEqualsWithDelta(12_000, $preview['total'], 0.001);
    }

    #[Test]
    public function a_student_who_joins_mid_month_is_prorated_by_day(): void
    {
        // §10.2 with the daily rule: 16 days of a 30 day September.
        app(SettingsService::class)->set('billing.proration_method', ProrationMethod::Daily->value);

        $this->enrol('S1', '2026-09-15');

        $preview = app(BillingService::class)->preview($this->company, Carbon::create(2026, 9, 1));

        // 15 Sep to 30 Sep inclusive is 16 days of 30.
        $this->assertEqualsWithDelta(round(4000 * 16 / 30), $preview['total'], 1.0);
        $this->assertTrue($preview['lines'][0]['students'][0]['is_partial_month']);
        $this->assertSame(16, $preview['lines'][0]['students'][0]['billable_days']);
    }

    #[Test]
    public function the_full_month_rule_charges_a_whole_month_for_a_late_joiner(): void
    {
        app(SettingsService::class)->set('billing.proration_method', ProrationMethod::FullMonth->value);

        $this->enrol('S1', '2026-09-28');

        $preview = app(BillingService::class)->preview($this->company, Carbon::create(2026, 9, 1));

        $this->assertEqualsWithDelta(4000, $preview['total'], 0.001);
    }

    #[Test]
    public function a_student_who_leaves_mid_month_is_billed_only_for_the_days_served(): void
    {
        app(SettingsService::class)->set('billing.proration_method', ProrationMethod::Daily->value);

        $this->enrol('S1', '2026-09-01', '2026-09-10');

        $preview = app(BillingService::class)->preview($this->company, Carbon::create(2026, 9, 1));

        $this->assertSame(10, $preview['lines'][0]['students'][0]['billable_days']);
        $this->assertStringContainsString('left', strtolower((string) $preview['lines'][0]['students'][0]['note']));
    }

    #[Test]
    public function a_student_who_left_before_the_month_is_not_billed_at_all(): void
    {
        $this->enrol('S1', '2026-09-01', '2026-09-30');

        $preview = app(BillingService::class)->preview($this->company, Carbon::create(2026, 10, 1));

        $this->assertFalse($preview['billable']);
        $this->assertStringContainsString('No billable students', $preview['reason']);
    }

    #[Test]
    public function per_student_amounts_always_add_up_to_the_invoice_total(): void
    {
        // Proration produces fractions; the parts must still sum to the whole.
        app(SettingsService::class)->set('billing.proration_method', ProrationMethod::Daily->value);

        foreach (range(1, 17) as $i) {
            $this->enrol('S'.$i, '2026-09-'.str_pad((string) (($i % 27) + 1), 2, '0', STR_PAD_LEFT));
        }

        $invoice = app(BillingService::class)->generateFor($this->company, Carbon::create(2026, 9, 1));

        $lineTotal = (float) $invoice->lines->sum('line_total');
        $studentTotal = (float) $invoice->studentDetails()->sum('amount');

        $this->assertEqualsWithDelta((float) $invoice->total, $lineTotal, 0.001);
        $this->assertEqualsWithDelta((float) $invoice->total, $studentTotal, 0.001);
    }

    #[Test]
    public function billing_is_generated_from_the_registry_not_typed_in(): void
    {
        // §3.1 — the chain company -> bus -> students is the source.
        $this->enrol('S1', '2026-09-01');
        $this->enrol('S2', '2026-09-01');

        $invoice = app(BillingService::class)->generateFor($this->company, Carbon::create(2026, 9, 1));

        $this->assertSame(2, $invoice->student_count);
        $this->assertSame($this->bus->id, $invoice->lines->first()->bus_id);
        $this->assertCount(2, $invoice->lines->first()->students);
    }

    #[Test]
    public function a_month_that_is_already_invoiced_is_not_billed_twice(): void
    {
        $this->enrol('S1', '2026-09-01');

        $first = app(BillingService::class)->generateFor($this->company, Carbon::create(2026, 9, 1));
        $second = app(BillingService::class)->generateFor($this->company, Carbon::create(2026, 9, 1));

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Invoice::count());
    }

    #[Test]
    public function a_month_outside_the_billing_months_of_the_year_is_skipped(): void
    {
        // §10.3 — billing pauses over the summer when the year says so.
        AcademicYear::where('name', '2026-2027')->update([
            'billing_mode' => BillingMode::Pause->value,
            'billable_months' => json_encode([9, 10, 11, 12, 1, 2, 3, 4, 5, 6]),
        ]);

        $this->enrol('S1', '2026-09-01');

        $july = app(BillingService::class)->preview($this->company, Carbon::create(2027, 7, 1));

        $this->assertFalse($july['billable']);
        $this->assertStringContainsString('outside the billing months', $july['reason']);
    }

    #[Test]
    public function the_proration_rule_in_force_is_frozen_onto_the_invoice(): void
    {
        app(SettingsService::class)->set('billing.proration_method', ProrationMethod::Daily->value);
        $this->enrol('S1', '2026-09-10');

        $invoice = app(BillingService::class)->generateFor($this->company, Carbon::create(2026, 9, 1));

        // Changing the setting afterwards must not restate a past invoice.
        app(SettingsService::class)->set('billing.proration_method', ProrationMethod::FullMonth->value);

        $this->assertSame(ProrationMethod::Daily, $invoice->fresh()->proration_method);
    }

    #[Test]
    public function issuing_an_invoice_debits_receivables_and_credits_revenue(): void
    {
        // §4.4 — a month already served is earned revenue.
        Carbon::setTestNow(Carbon::create(2026, 9, 30));

        $this->enrol('S1', '2026-09-01');
        $invoice = app(BillingService::class)->generateFor($this->company, Carbon::create(2026, 9, 1));

        $issued = app(InvoiceService::class)->issue($invoice);
        $journal = $issued->journal;

        $this->assertSame(DocumentStatus::Issued, $issued->status);
        $this->assertTrue($journal->isPosted());

        $receivable = $journal->lines->firstWhere('account_id', Account::byCode('1030')->id);
        $revenue = $journal->lines->firstWhere('account_id', Account::byCode('4010')->id);

        $this->assertEqualsWithDelta(4000, (float) $receivable->debit, 0.001);
        $this->assertEqualsWithDelta(4000, (float) $revenue->credit, 0.001);
        $this->assertTrue($issued->revenue_recognised);

        Carbon::setTestNow();
    }

    #[Test]
    public function a_month_billed_in_advance_credits_deferred_revenue(): void
    {
        // §4.4 — billed before the service is delivered.
        Carbon::setTestNow(Carbon::create(2026, 9, 1));

        $this->enrol('S1', '2026-09-01');
        $invoice = app(BillingService::class)->generateFor(
            $this->company,
            Carbon::create(2026, 11, 1),
            Carbon::create(2026, 9, 1),
        );

        $issued = app(InvoiceService::class)->issue($invoice);

        $deferred = $issued->journal->lines->firstWhere('account_id', Account::byCode('2060')->id);

        $this->assertTrue($issued->is_deferred);
        $this->assertFalse($issued->revenue_recognised);
        $this->assertNotNull($deferred, 'Advance billing should credit 2060 deferred revenue.');

        Carbon::setTestNow();
    }

    #[Test]
    public function a_company_with_a_special_rate_is_billed_at_that_rate(): void
    {
        // §4.2 — "Different rates may be assigned to different bus companies
        // where a special agreement exists."
        RateCard::create([
            'academic_year_id' => AcademicYear::where('name', '2026-2027')->value('id'),
            'bus_company_id' => $this->company->id,
            'amount' => 3500,
            'effective_from' => '2026-09-01',
            'effective_to' => '2027-08-31',
            'is_active' => true,
        ]);

        $this->enrol('S1', '2026-09-01');

        $preview = app(BillingService::class)->preview($this->company, Carbon::create(2026, 9, 1));

        $this->assertEqualsWithDelta(3500, $preview['rate'], 0.001);
    }

    #[Test]
    public function a_historical_month_keeps_the_rate_that_applied_at_the_time(): void
    {
        // §4.2 — a future price increase must not restate a past month.
        RateCard::create([
            'academic_year_id' => AcademicYear::where('name', '2026-2027')->value('id'),
            'bus_company_id' => null,
            'amount' => 5000,
            'effective_from' => '2027-01-01',
            'is_active' => true,
        ]);

        $resolver = app(RateResolver::class);

        $this->assertEqualsWithDelta(4000, $resolver->amountFor($this->company, Carbon::create(2026, 10, 31)), 0.001);
        $this->assertEqualsWithDelta(5000, $resolver->amountFor($this->company, Carbon::create(2027, 2, 28)), 0.001);
    }
}
