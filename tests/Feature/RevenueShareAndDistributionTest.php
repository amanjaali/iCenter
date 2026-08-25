<?php

namespace Tests\Feature;

use App\Enums\RevenueShareBasis;
use App\Models\Account;
use App\Models\Partner;
use App\Models\Period;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use App\Services\Partners\DistributionService;
use App\Services\Partners\RevenueShareService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Scope of work §5 and §10.1 — the 50/50 split with Cyber Gate and the
 * distribution of net profit to the five partners.
 */
class RevenueShareAndDistributionTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installChartOfAccounts();
        $this->journals = app(JournalService::class);
    }

    /** Post revenue and operating expenses into September 2026. */
    private function tradeSeptember(float $revenue, float $expenses): void
    {
        $this->journals->post(
            Carbon::create(2026, 9, 30),
            'Subscription revenue',
            [
                ['account' => '1030', 'debit' => $revenue],
                ['account' => '4010', 'credit' => $revenue, 'project_id' => $this->project()->id],
            ],
        );

        if ($expenses > 0) {
            $this->journals->post(
                Carbon::create(2026, 9, 30),
                'Operating expenses',
                [
                    [
                        'account' => '7010',
                        'debit' => $expenses,
                        'department_id' => $this->department('ADM')->id,
                        'project_id' => $this->project()->id,
                    ],
                    ['account' => '1020', 'credit' => $expenses],
                ],
            );
        }
    }

    #[Test]
    public function the_gross_basis_takes_half_of_revenue_before_expenses(): void
    {
        // §10.1, the treatment the scope of work assumes.
        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Gross->value);

        $this->tradeSeptember(revenue: 20_000_000, expenses: 6_000_000);

        $period = Period::forDate(Carbon::create(2026, 9, 30));
        $calculation = app(RevenueShareService::class)->calculate($period, $this->project());

        $this->assertSame(RevenueShareBasis::Gross, $calculation['basis']);
        $this->assertEqualsWithDelta(20_000_000, $calculation['revenue_base'], 0.001);
        $this->assertEqualsWithDelta(10_000_000, $calculation['share_amount'], 0.001);
    }

    #[Test]
    public function the_net_basis_deducts_expenses_before_splitting(): void
    {
        // §10.1, the alternative treatment. The point of the setting is that
        // both are supported without a rebuild.
        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Net->value);

        $this->tradeSeptember(revenue: 20_000_000, expenses: 6_000_000);

        $period = Period::forDate(Carbon::create(2026, 9, 30));
        $calculation = app(RevenueShareService::class)->calculate($period, $this->project());

        $this->assertSame(RevenueShareBasis::Net, $calculation['basis']);
        $this->assertEqualsWithDelta(14_000_000, $calculation['revenue_base'], 0.001);
        $this->assertEqualsWithDelta(7_000_000, $calculation['share_amount'], 0.001);
    }

    #[Test]
    public function a_loss_produces_no_share_to_pay(): void
    {
        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Net->value);

        $this->tradeSeptember(revenue: 5_000_000, expenses: 9_000_000);

        $period = Period::forDate(Carbon::create(2026, 9, 30));
        $calculation = app(RevenueShareService::class)->calculate($period, $this->project());

        $this->assertEqualsWithDelta(0, $calculation['share_amount'], 0.001);
    }

    #[Test]
    public function posting_the_share_debits_the_direct_cost_and_credits_the_payable(): void
    {
        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Gross->value);
        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $service = app(RevenueShareService::class);
        $period = Period::forDate(Carbon::create(2026, 9, 30));

        $run = $service->post($service->prepare($period, $this->project()));
        $journal = $run->journal;

        $cost = $journal->lines->firstWhere('account_id', Account::byCode('5010')->id);
        $payable = $journal->lines->firstWhere('account_id', Account::byCode('2040')->id);

        $this->assertEqualsWithDelta(10_000_000, (float) $cost->debit, 0.001);
        $this->assertEqualsWithDelta(10_000_000, (float) $payable->credit, 0.001);
        $this->assertSame('posted', $run->status);
    }

    #[Test]
    public function the_basis_is_frozen_on_a_posted_run(): void
    {
        // Changing the setting must never restate a period already posted.
        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Gross->value);
        $this->tradeSeptember(revenue: 20_000_000, expenses: 6_000_000);

        $service = app(RevenueShareService::class);
        $period = Period::forDate(Carbon::create(2026, 9, 30));
        $run = $service->post($service->prepare($period, $this->project()));

        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Net->value);

        $run->refresh();
        $this->assertSame(RevenueShareBasis::Gross, $run->basis);
        $this->assertEqualsWithDelta(10_000_000, (float) $run->share_amount, 0.001);
    }

    #[Test]
    public function paying_the_share_clears_the_payable(): void
    {
        app(SettingsService::class)->set('revenue_share.basis', RevenueShareBasis::Gross->value);
        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $service = app(RevenueShareService::class);
        $period = Period::forDate(Carbon::create(2026, 9, 30));
        $run = $service->post($service->prepare($period, $this->project()));

        $service->pay($run, Carbon::create(2026, 10, 10), 10_000_000, Account::byCode('1020'));

        $run->refresh();
        $this->assertSame('settled', $run->status);
        $this->assertEqualsWithDelta(0, $run->outstandingAmount(), 0.001);
    }

    #[Test]
    public function net_profit_is_split_twenty_percent_to_each_of_the_five_partners(): void
    {
        // Scope of work §5.2.
        $this->tradeSeptember(revenue: 20_000_000, expenses: 5_000_000);

        $calculation = app(DistributionService::class)->calculate(
            Carbon::create(2026, 9, 1),
            Carbon::create(2026, 9, 30),
        );

        $this->assertEqualsWithDelta(15_000_000, $calculation['net_profit'], 0.001);
        $this->assertCount(5, $calculation['shares']);
        $this->assertEqualsWithDelta(100.0, $calculation['total_percent'], 0.001);

        foreach ($calculation['shares'] as $share) {
            $this->assertEqualsWithDelta(3_000_000, $share['amount'], 0.001);
        }
    }

    #[Test]
    public function partner_shares_always_add_back_to_the_distributable_amount(): void
    {
        // An amount that does not divide evenly by five must still reconcile.
        $this->tradeSeptember(revenue: 10_000_003, expenses: 0);

        $service = app(DistributionService::class);
        $run = $service->prepare(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30));

        $this->assertEqualsWithDelta(
            (float) $run->distributable_amount,
            (float) $run->lines()->sum('share_amount'),
            0.001,
        );
    }

    #[Test]
    public function ownership_percentages_are_configurable_and_change_the_split(): void
    {
        // §2 — "Partner ownership percentages must be configurable... without
        // rebuilding the distribution logic."
        $service = app(DistributionService::class);
        $partners = Partner::orderBy('sort_order')->get();

        // A 40 / 15 / 15 / 15 / 15 shareholding, still totalling 100%.
        $service->changeOwnership($partners[0], 40, Carbon::create(2026, 9, 1), 'Bought out a departing partner');
        $service->changeOwnership($partners[1], 15, Carbon::create(2026, 9, 1), 'Diluted on the buy-out');
        $service->changeOwnership($partners[2], 15, Carbon::create(2026, 9, 1), 'Diluted on the buy-out');
        $service->changeOwnership($partners[3], 15, Carbon::create(2026, 9, 1), 'Diluted on the buy-out');
        $service->changeOwnership($partners[4], 15, Carbon::create(2026, 9, 1), 'Diluted on the buy-out');

        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $calculation = $service->calculate(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30));
        $shares = $calculation['shares']->keyBy(fn (array $s) => $s['partner']->code);

        $this->assertEqualsWithDelta(100.0, $calculation['total_percent'], 0.001);
        $this->assertEqualsWithDelta(8_000_000, $shares['P1']['amount'], 0.001);
        $this->assertEqualsWithDelta(3_000_000, $shares['P2']['amount'], 0.001);
        $this->assertEqualsWithDelta(3_000_000, $shares['P4']['amount'], 0.001);
        $this->assertEqualsWithDelta(20_000_000, $calculation['distributable'], 0.001);
    }

    #[Test]
    public function a_shareholding_that_does_not_total_one_hundred_still_distributes_the_whole_amount(): void
    {
        // Percentages are data and can be mis-keyed. The distribution shares by
        // relative weight, so the full distributable amount is always allocated
        // rather than partly disappearing — and totalOwnership() lets the
        // Partners screen flag the discrepancy.
        $service = app(DistributionService::class);
        $service->changeOwnership(Partner::orderBy('sort_order')->first(), 40, Carbon::create(2026, 9, 1), 'Typo under test');

        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $calculation = $service->calculate(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30));

        $this->assertEqualsWithDelta(120.0, $service->totalOwnership(), 0.001);
        $this->assertEqualsWithDelta(
            $calculation['distributable'],
            $calculation['shares']->sum('amount'),
            0.001,
        );
    }

    #[Test]
    public function an_ownership_change_is_logged_with_the_user_and_a_reason(): void
    {
        // Scope of work §9.3.
        $partner = Partner::orderBy('sort_order')->first();

        app(DistributionService::class)->changeOwnership(
            $partner,
            30,
            Carbon::create(2026, 9, 1),
            'Additional capital contributed',
        );

        $this->assertDatabaseHas('ownership_change_logs', [
            'partner_id' => $partner->id,
            'old_percent' => '20.0000',
            'new_percent' => '30.0000',
            'reason' => 'Additional capital contributed',
        ]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ownership_changed']);
    }

    #[Test]
    public function a_posted_distribution_keeps_the_percentage_used_at_the_time(): void
    {
        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $service = app(DistributionService::class);
        $run = $service->post($service->prepare(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30)));

        // A later change of shareholding must not restate the declaration.
        $service->changeOwnership(Partner::first(), 60, Carbon::create(2026, 10, 1), 'Later restructuring');

        $line = $run->fresh('lines')->lines->firstWhere('partner_id', Partner::first()->id);

        $this->assertEqualsWithDelta(20.0, (float) $line->ownership_percent, 0.001);
        $this->assertEqualsWithDelta(4_000_000, (float) $line->share_amount, 0.001);
    }

    #[Test]
    public function paying_a_partner_reduces_what_is_still_outstanding(): void
    {
        // §5.2 — record, per partner, the share earned, paid, and outstanding.
        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $service = app(DistributionService::class);
        $run = $service->post($service->prepare(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30)));
        $line = $run->fresh('lines')->lines->first();

        $service->payout($line, Carbon::create(2026, 10, 5), 1_500_000, Account::byCode('1020'));

        $line->refresh();
        $this->assertEqualsWithDelta(1_500_000, (float) $line->paid_amount, 0.001);
        $this->assertEqualsWithDelta(2_500_000, (float) $line->outstanding_amount, 0.001);

        $service->payout($line, Carbon::create(2026, 10, 20), 2_500_000, Account::byCode('1020'));

        $line->refresh();
        $this->assertEqualsWithDelta(0, (float) $line->outstanding_amount, 0.001);
    }

    #[Test]
    public function a_partner_cannot_be_paid_more_than_they_are_owed(): void
    {
        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $service = app(DistributionService::class);
        $run = $service->post($service->prepare(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30)));
        $line = $run->fresh('lines')->lines->first();

        $this->expectException(\App\Services\Accounting\PostingException::class);
        $service->payout($line, Carbon::create(2026, 10, 5), 9_000_000, Account::byCode('1020'));
    }

    #[Test]
    public function retained_profit_is_excluded_from_the_distribution(): void
    {
        $this->tradeSeptember(revenue: 20_000_000, expenses: 0);

        $calculation = app(DistributionService::class)->calculate(
            Carbon::create(2026, 9, 1),
            Carbon::create(2026, 9, 30),
            retained: 5_000_000,
        );

        $this->assertEqualsWithDelta(15_000_000, $calculation['distributable'], 0.001);
        $this->assertEqualsWithDelta(3_000_000, $calculation['shares'][0]['amount'], 0.001);
    }
}
