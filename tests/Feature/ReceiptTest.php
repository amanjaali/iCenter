<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\ProrationMethod;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Bus;
use App\Models\BusCompany;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Scope of work §4.3 — the receipt side of the invoice-to-payment cycle: what
 * the bus company is given when it pays, and that it agrees with the invoice
 * it settles.
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private BusCompany $company;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installChartOfAccounts();

        $this->company = BusCompany::create([
            'code' => 'BC-R1',
            'name' => 'Al-Noor Transport',
            'payment_terms_days' => 15,
            'status' => 'active',
        ]);

        Bus::create([
            'bus_company_id' => $this->company->id,
            'code' => 'BUS-R1',
            'capacity' => 50,
            'status' => 'active',
        ]);

        // A 480,000 invoice — 120 students at 4,000 — raised and issued.
        $this->invoice = $this->issuedInvoice(480_000);
    }

    private function issuedInvoice(float $total): Invoice
    {
        $invoice = Invoice::create([
            'number' => 'INV-TEST-1',
            'bus_company_id' => $this->company->id,
            'period_id' => $this->periods()->resolveFor(Carbon::parse('2026-08-31'))->id,
            'billing_month' => '2026-08-01',
            'issue_date' => '2026-08-31',
            'due_date' => '2026-09-15',
            'subtotal' => $total,
            'discount_amount' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'balance_due' => $total,
            'status' => 'issued',
            'proration_method' => ProrationMethod::Daily,
        ]);

        return $invoice->refresh();
    }

    private function bank(): Account
    {
        return Account::system('bank');
    }

    private function record(float $amount, string $date, ?float $applied = null): Payment
    {
        return app(PaymentService::class)->record(
            $this->company,
            Carbon::parse($date),
            $amount,
            $this->bank(),
            [['invoice_id' => $this->invoice->id, 'amount' => $applied ?? $amount]],
            'bank',
            'TRF-'.substr($date, -2),
        );
    }

    #[Test]
    public function a_part_payment_leaves_the_invoice_open_for_the_balance(): void
    {
        $receipt = $this->record(300_000, '2026-09-08');

        $this->assertSame('posted', $receipt->status);
        $this->assertTrue(Money::equals(300_000, $receipt->allocated_amount));
        $this->assertTrue(Money::equals(0, $receipt->unallocated_amount));

        $this->invoice->refresh();
        $this->assertTrue(Money::equals(300_000, $this->invoice->amount_paid));
        $this->assertTrue(Money::equals(180_000, $this->invoice->balance_due));
        $this->assertNotSame(DocumentStatus::Paid, $this->invoice->status, 'A part-paid invoice is not settled.');
    }

    #[Test]
    public function the_closing_payment_settles_the_invoice_in_full(): void
    {
        $this->record(300_000, '2026-09-08');
        $this->record(180_000, '2026-09-22');

        $this->invoice->refresh();
        $this->assertTrue(Money::equals(480_000, $this->invoice->amount_paid));
        $this->assertTrue(Money::equals(0, $this->invoice->balance_due));
        $this->assertSame(DocumentStatus::Paid, $this->invoice->status);
    }

    #[Test]
    public function each_receipt_debits_the_bank_and_credits_receivables(): void
    {
        $receipt = $this->record(300_000, '2026-09-08');
        $lines = $receipt->journal->lines()->with('account')->get();

        $bank = $lines->firstWhere('account.code', $this->bank()->code);
        $receivable = $lines->firstWhere('account.code', Account::system('receivable')->code);

        $this->assertTrue(Money::equals(300_000, $bank->debit));
        $this->assertTrue(Money::equals(300_000, $receivable->credit));
        $this->assertTrue(Money::equals($lines->sum('debit'), $lines->sum('credit')));
    }

    #[Test]
    public function the_printed_receipt_states_the_amount_in_figures_and_words(): void
    {
        $receipt = $this->record(300_000, '2026-09-08');

        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))
            ->get(route('payments.print', $receipt));

        $response->assertOk()
            ->assertSee($receipt->number)
            ->assertSee('Al-Noor Transport')
            ->assertSee('300,000 IQD')
            ->assertSee('Three hundred thousand Iraqi Dinars only')
            ->assertSee($this->invoice->number)
            ->assertSee('Official receipt');
    }

    #[Test]
    public function the_printed_receipt_reports_what_is_still_owed(): void
    {
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);

        $part = $this->record(300_000, '2026-09-08');
        $this->actingAs($accountant)->get(route('payments.print', $part))
            ->assertSee('180,000 IQD')
            ->assertSee('remains outstanding on this account');

        $final = $this->record(180_000, '2026-09-22');
        $this->actingAs($accountant)->get(route('payments.print', $final))
            ->assertSee('The account is settled in full as at the date of this receipt.');
    }

    #[Test]
    public function a_voided_receipt_still_prints_but_says_so(): void
    {
        $receipt = $this->record(300_000, '2026-09-08');
        app(PaymentService::class)->void($receipt, 'Cheque returned unpaid.');

        $this->invoice->refresh();
        $this->assertTrue(
            Money::equals(480_000, $this->invoice->balance_due),
            'Voiding a receipt reopens the balance it had settled.'
        );

        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))
            ->get(route('payments.print', $receipt->refresh()))
            ->assertOk()
            ->assertSee('This receipt has been voided');
    }

    #[Test]
    public function money_paid_ahead_of_billing_is_held_rather_than_forced_onto_an_invoice(): void
    {
        $receipt = app(PaymentService::class)->record(
            $this->company,
            Carbon::parse('2026-09-08'),
            600_000,
            $this->bank(),
            [['invoice_id' => $this->invoice->id, 'amount' => 480_000]],
            'bank',
        );

        $this->assertTrue(Money::equals(480_000, $receipt->allocated_amount));
        $this->assertTrue(Money::equals(120_000, $receipt->unallocated_amount));

        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))
            ->get(route('payments.print', $receipt))
            ->assertOk()
            ->assertSee('Held in advance');
    }

    #[Test]
    public function operations_staff_cannot_open_a_receipt(): void
    {
        $receipt = $this->record(300_000, '2026-09-08');

        $this->actingAs(User::factory()->create(['role' => UserRole::Operations]))
            ->get(route('payments.print', $receipt))
            ->assertForbidden();
    }
}
