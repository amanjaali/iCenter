<?php

namespace Tests\Feature;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\Journal;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PostingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The rules the scope of work asks the ledger to enforce rather than trust the
 * operator on: balance, open periods, the three tags of §7, and §9.3's
 * "corrections are made by reversing entries, not by deletion".
 */
class JournalPostingTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installChartOfAccounts();
        $this->journals = app(JournalService::class);
    }

    #[Test]
    public function a_balanced_journal_posts_and_moves_the_ledger(): void
    {
        $journal = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Test entry',
            [
                ['account' => '1020', 'debit' => 500_000, 'description' => 'Bank in'],
                ['account' => '4090', 'credit' => 500_000, 'description' => 'Other income'],
            ],
        );

        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertTrue($journal->isBalanced());
        $this->assertEqualsWithDelta(500_000, (float) $journal->total_debit, 0.001);
        $this->assertCount(2, $journal->lines);
        $this->assertNotNull($journal->posted_at);
    }

    #[Test]
    public function an_unbalanced_journal_is_refused(): void
    {
        $this->expectException(PostingException::class);
        $this->expectExceptionMessageMatches('/does not balance/');

        $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Deliberately unbalanced',
            [
                ['account' => '1020', 'debit' => 500_000],
                ['account' => '4090', 'credit' => 400_000],
            ],
        );
    }

    #[Test]
    public function a_line_cannot_carry_both_a_debit_and_a_credit(): void
    {
        $this->expectException(PostingException::class);
        $this->expectExceptionMessageMatches('/both a debit and a credit/');

        $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Both sides',
            [
                ['account' => '1020', 'debit' => 100, 'credit' => 100],
                ['account' => '4090', 'credit' => 100],
            ],
        );
    }

    #[Test]
    public function a_single_line_journal_is_refused(): void
    {
        $this->expectException(PostingException::class);

        $this->journals->post(
            Carbon::create(2026, 9, 15),
            'One line',
            [['account' => '1020', 'debit' => 100]],
        );
    }

    #[Test]
    public function an_expense_account_demands_a_cost_centre_and_a_project(): void
    {
        // Scope of work §7 — every expense entry carries three tags.
        $this->expectException(PostingException::class);
        $this->expectExceptionMessageMatches('/requires a (cost centre|project)/');

        $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Untagged expense',
            [
                ['account' => '7010', 'debit' => 250_000],
                ['account' => '1020', 'credit' => 250_000],
            ],
        );
    }

    #[Test]
    public function a_tagged_expense_posts(): void
    {
        $journal = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Office rent',
            [
                [
                    'account' => '7010',
                    'debit' => 250_000,
                    'department_id' => $this->department('ADM')->id,
                    'project_id' => $this->project('CORP')->id,
                ],
                ['account' => '1020', 'credit' => 250_000],
            ],
        );

        $this->assertTrue($journal->isPosted());
    }

    #[Test]
    public function a_closed_period_refuses_new_postings(): void
    {
        $period = $this->periods()->resolveFor(Carbon::create(2026, 9, 15));
        $this->periods()->close($period);

        $this->expectException(PostingException::class);
        $this->expectExceptionMessageMatches('/is not open/');

        $this->journals->post(
            Carbon::create(2026, 9, 20),
            'Into a closed month',
            [
                ['account' => '1020', 'debit' => 100_000],
                ['account' => '4090', 'credit' => 100_000],
            ],
        );
    }

    #[Test]
    public function a_period_with_draft_journals_will_not_close(): void
    {
        $this->journals->createDraft(
            Carbon::create(2026, 9, 10),
            'Still a draft',
            [
                ['account' => '1020', 'debit' => 100_000],
                ['account' => '4090', 'credit' => 100_000],
            ],
        );

        $period = $this->periods()->resolveFor(Carbon::create(2026, 9, 10));
        $result = $this->periods()->close($period);

        $this->assertFalse($result['closed']);
        $this->assertStringContainsString('draft journal', $result['message']);
    }

    #[Test]
    public function reversing_a_journal_creates_a_mirror_entry_and_leaves_the_original(): void
    {
        // Scope of work §9.3 — corrections are reversals, not deletions.
        $original = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'To be reversed',
            [
                ['account' => '1020', 'debit' => 750_000],
                ['account' => '4090', 'credit' => 750_000],
            ],
        );

        $reversal = $this->journals->reverse($original, 'Entered against the wrong company');

        $original->refresh();

        $this->assertSame(JournalStatus::Reversed, $original->status);
        $this->assertSame($reversal->id, $original->reversed_by_id);
        $this->assertSame($original->id, $reversal->reversal_of_id);
        $this->assertSame(JournalSource::Reversal, $reversal->source_type);

        // The sides are swapped.
        $bank = Account::byCode('1020');
        $originalBankLine = $original->lines->firstWhere('account_id', $bank->id);
        $reversalBankLine = $reversal->lines->firstWhere('account_id', $bank->id);

        $this->assertEqualsWithDelta(750_000, (float) $originalBankLine->debit, 0.001);
        $this->assertEqualsWithDelta(750_000, (float) $reversalBankLine->credit, 0.001);

        // Both journals survive: nothing is deleted.
        $this->assertDatabaseHas('journals', ['id' => $original->id]);
        $this->assertSame(2, Journal::count());
    }

    #[Test]
    public function a_journal_cannot_be_reversed_twice(): void
    {
        $journal = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Reverse once',
            [
                ['account' => '1020', 'debit' => 100_000],
                ['account' => '4090', 'credit' => 100_000],
            ],
        );

        $this->journals->reverse($journal, 'First correction');

        $this->expectException(PostingException::class);
        $this->journals->reverse($journal->refresh(), 'Second attempt');
    }

    #[Test]
    public function a_posted_journal_cannot_be_deleted(): void
    {
        $journal = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Posted and permanent',
            [
                ['account' => '1020', 'debit' => 100_000],
                ['account' => '4090', 'credit' => 100_000],
            ],
        );

        $this->expectException(PostingException::class);
        $this->expectExceptionMessageMatches('/Reverse it instead/');

        $this->journals->deleteDraft($journal);
    }

    #[Test]
    public function a_reversal_lands_in_the_current_month_when_the_original_month_is_closed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 10));

        $original = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'September entry',
            [
                ['account' => '1020', 'debit' => 300_000],
                ['account' => '4090', 'credit' => 300_000],
            ],
        );

        $this->periods()->close($this->periods()->resolveFor(Carbon::create(2026, 9, 15)));

        $reversal = $this->journals->reverse($original, 'Discovered in November');

        // The closed month stays closed; the correction lands in November.
        $this->assertSame('2026-11', $reversal->period->code);

        Carbon::setTestNow();
    }

    #[Test]
    public function every_posting_is_written_to_the_audit_trail(): void
    {
        // Scope of work §9.3.
        $journal = $this->journals->post(
            Carbon::create(2026, 9, 15),
            'Audited',
            [
                ['account' => '1020', 'debit' => 100_000],
                ['account' => '4090', 'credit' => 100_000],
            ],
        );

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'posted',
            'auditable_type' => Journal::class,
            'auditable_id' => $journal->id,
        ]);
    }
}
