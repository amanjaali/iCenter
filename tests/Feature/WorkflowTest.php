<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Bus;
use App\Models\BusCompany;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end checks through the HTTP layer: the screens an accountant actually
 * uses, rather than the services underneath them.
 */
class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private BusCompany $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installChartOfAccounts();

        $this->accountant = User::factory()->create(['role' => UserRole::Accountant]);

        $this->company = BusCompany::create([
            'code' => 'BC-W1', 'name' => 'Workflow Transport',
            'payment_terms_days' => 15, 'status' => 'active',
        ]);

        $bus = Bus::create([
            'bus_company_id' => $this->company->id, 'code' => 'BUS-01',
            'capacity' => 30, 'status' => 'active',
        ]);

        foreach (range(1, 5) as $i) {
            $student = Student::create([
                'bus_company_id' => $this->company->id, 'bus_id' => $bus->id,
                'code' => 'STU-W'.$i, 'name' => 'Student '.$i,
                'joined_on' => '2026-09-01', 'status' => 'active',
            ]);

            StudentEnrollment::create([
                'student_id' => $student->id, 'bus_id' => $bus->id,
                'bus_company_id' => $this->company->id,
                'start_date' => '2026-09-01', 'is_billable' => true,
            ]);
        }
    }

    #[Test]
    public function an_accountant_can_generate_issue_and_collect_a_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 30));

        // Generate
        $this->actingAs($this->accountant)
            ->post('/invoices/generate', ['month' => '2026-09-01'])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $this->assertSame(DocumentStatus::Draft, $invoice->status);
        $this->assertSame(5, $invoice->student_count);
        $this->assertEqualsWithDelta(20_000, (float) $invoice->total, 0.01);

        // Nothing has reached the ledger yet.
        $this->assertSame(0, Journal::count());

        // Issue
        $this->actingAs($this->accountant)
            ->post("/invoices/{$invoice->id}/issue")
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(DocumentStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->journal_id);
        $this->assertTrue($invoice->journal->isPosted());

        // Collect in full
        $this->actingAs($this->accountant)->post('/payments', [
            'bus_company_id' => $this->company->id,
            'payment_date' => '2026-10-10',
            'amount' => 20_000,
            'deposit_account_id' => Account::byCode('1020')->id,
            'method' => 'bank',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 20_000]],
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(DocumentStatus::Paid, $invoice->status);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.01);

        Carbon::setTestNow();
    }

    #[Test]
    public function generating_the_same_month_twice_does_not_duplicate_the_invoice(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 30));

        $this->actingAs($this->accountant)->post('/invoices/generate', ['month' => '2026-09-01']);
        $this->actingAs($this->accountant)->post('/invoices/generate', ['month' => '2026-09-01']);

        $this->assertSame(1, Invoice::count());

        Carbon::setTestNow();
    }

    #[Test]
    public function a_manual_journal_can_be_posted_from_the_form(): void
    {
        $this->actingAs($this->accountant)->post('/journals', [
            'journal_date' => '2026-09-15',
            'memo' => 'Bank interest received',
            'post_now' => '1',
            'lines' => [
                ['account_id' => Account::byCode('1020')->id, 'debit' => 50_000, 'credit' => null],
                ['account_id' => Account::byCode('4090')->id, 'debit' => null, 'credit' => 50_000],
                // A blank row: the form always renders a few, and they must be
                // dropped rather than rejected.
                ['account_id' => null, 'debit' => null, 'credit' => null],
            ],
        ])->assertRedirect();

        $journal = Journal::firstOrFail();

        $this->assertTrue($journal->isPosted());
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(50_000, (float) $journal->total_debit, 0.01);
    }

    #[Test]
    public function an_unbalanced_journal_is_rejected_with_a_readable_message(): void
    {
        $this->actingAs($this->accountant)->post('/journals', [
            'journal_date' => '2026-09-15',
            'memo' => 'Deliberately unbalanced',
            'post_now' => '1',
            'lines' => [
                ['account_id' => Account::byCode('1020')->id, 'debit' => 50_000],
                ['account_id' => Account::byCode('4090')->id, 'credit' => 40_000],
            ],
        ])->assertSessionHasErrors('posting');

        $this->assertSame(0, Journal::count());
    }

    #[Test]
    public function an_expense_without_a_cost_centre_is_refused_by_the_form(): void
    {
        // Scope of work §7 — the three tags are mandatory.
        $this->actingAs($this->accountant)->post('/expenses', [
            'expense_date' => '2026-09-15',
            'account_id' => Account::byCode('7010')->id,
            'description' => 'Office rent',
            'amount' => 3_000_000,
            'nature' => 'fixed',
            'treatment' => 'opex',
            'payment_status' => 'paid',
            'paid_from_account_id' => Account::byCode('1020')->id,
        ])->assertSessionHasErrors(['department_id', 'project_id']);

        $this->assertSame(0, Expense::count());
    }

    #[Test]
    public function a_fully_tagged_expense_posts_from_the_form(): void
    {
        $this->actingAs($this->accountant)->post('/expenses', [
            'expense_date' => '2026-09-15',
            'account_id' => Account::byCode('7010')->id,
            'department_id' => $this->department('ADM')->id,
            'project_id' => $this->project('CORP')->id,
            'description' => 'Office rent — September',
            'amount' => 3_000_000,
            'nature' => 'fixed',
            'treatment' => 'opex',
            'payment_status' => 'paid',
            'paid_from_account_id' => Account::byCode('1020')->id,
            'post_now' => '1',
        ])->assertRedirect();

        $expense = Expense::firstOrFail();

        $this->assertTrue($expense->isPosted());
        $this->assertNotNull($expense->journal_id);
        $this->assertEqualsWithDelta(3_000_000, (float) $expense->total, 0.01);
    }

    #[Test]
    public function a_rate_cannot_be_changed_without_a_reason(): void
    {
        // Scope of work §4.2.
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $year = \App\Models\AcademicYear::firstOrFail();

        $this->actingAs($administrator)->post('/rates', [
            'academic_year_id' => $year->id,
            'amount' => 5000,
            'effective_from' => '2027-09-01',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($administrator)->post('/rates', [
            'academic_year_id' => $year->id,
            'amount' => 5000,
            'effective_from' => '2027-09-01',
            'reason' => 'Price increase agreed for 2027-2028',
        ])->assertRedirect();

        $this->assertDatabaseHas('rate_change_logs', [
            'action' => 'created',
            'reason' => 'Price increase agreed for 2027-2028',
        ]);
    }

    #[Test]
    public function a_student_recorded_as_left_stops_being_billed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 31));

        $student = Student::firstOrFail();

        $this->actingAs($this->accountant)->post("/students/{$student->id}/withdraw", [
            'left_on' => '2026-09-30',
            'end_reason' => 'left',
        ])->assertRedirect();

        $this->actingAs($this->accountant)->post('/invoices/generate', ['month' => '2026-10-01']);

        $october = Invoice::forMonth(Carbon::create(2026, 10, 1))->firstOrFail();

        // Four remain, not five.
        $this->assertSame(4, $october->student_count);

        Carbon::setTestNow();
    }
}
