<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Extends the Laravel test case rather than PHPUnit's: Money::format reads the
 * currency's display precision from config.
 */
class MoneyTest extends TestCase
{
    #[Test]
    public function five_equal_partners_shares_add_back_to_the_declared_amount(): void
    {
        // Scope of work §5.2 — five partners at 20% each. A naive 20% of an
        // amount that does not divide by five loses or invents a dinar.
        $shares = Money::allocate(1_000_001, [20, 20, 20, 20, 20]);

        $this->assertCount(5, $shares);
        $this->assertEqualsWithDelta(1_000_001, array_sum($shares), 0.001);
    }

    #[Test]
    public function allocation_respects_uneven_ownership(): void
    {
        $shares = Money::allocate(1_000_000, ['a' => 50, 'b' => 30, 'c' => 20]);

        $this->assertEqualsWithDelta(500_000, $shares['a'], 0.001);
        $this->assertEqualsWithDelta(300_000, $shares['b'], 0.001);
        $this->assertEqualsWithDelta(200_000, $shares['c'], 0.001);
        $this->assertEqualsWithDelta(1_000_000, array_sum($shares), 0.001);
    }

    #[Test]
    public function allocation_of_an_indivisible_amount_still_totals_exactly(): void
    {
        foreach ([7, 13, 99, 1_234_567] as $total) {
            $shares = Money::allocate($total, [1, 1, 1]);

            $this->assertEqualsWithDelta(
                $total,
                array_sum($shares),
                0.001,
                "Allocating {$total} across three equal weights did not total exactly.",
            );
        }
    }

    #[Test]
    public function allocation_with_no_weight_returns_zeroes_rather_than_dividing_by_zero(): void
    {
        $shares = Money::allocate(500_000, ['a' => 0, 'b' => 0]);

        $this->assertSame([0.0, 0.0], array_values($shares));
    }

    #[Test]
    public function whole_rounds_to_dinars(): void
    {
        // Iraqi Dinar has no minor units in circulation.
        $this->assertSame(4000.0, Money::whole(3999.6));
        $this->assertSame(1290.0, Money::whole(1290.4));
    }

    #[Test]
    public function accounting_format_shows_negatives_in_parentheses_and_zero_as_a_dash(): void
    {
        $this->assertSame('(1,500)', Money::accounting(-1500));
        $this->assertSame('—', Money::accounting(0));
        $this->assertSame('1,500', Money::accounting(1500));
    }
}
