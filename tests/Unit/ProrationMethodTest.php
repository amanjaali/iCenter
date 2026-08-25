<?php

namespace Tests\Unit;

use App\Enums\ProrationMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Scope of work §10.2 — the mid-month join and leave rule. All three
 * treatments are implemented so the client's decision is a setting, not a
 * rebuild.
 */
class ProrationMethodTest extends TestCase
{
    #[Test]
    public function daily_prorates_by_days_present(): void
    {
        $this->assertEqualsWithDelta(0.5, ProrationMethod::Daily->units(15, 30), 0.0001);
        $this->assertEqualsWithDelta(1.0, ProrationMethod::Daily->units(30, 30), 0.0001);
        $this->assertEqualsWithDelta(0.333333, ProrationMethod::Daily->units(10, 30), 0.0001);
    }

    #[Test]
    public function full_month_charges_a_whole_month_for_any_presence(): void
    {
        $this->assertEqualsWithDelta(1.0, ProrationMethod::FullMonth->units(1, 31), 0.0001);
        $this->assertEqualsWithDelta(1.0, ProrationMethod::FullMonth->units(31, 31), 0.0001);
    }

    #[Test]
    public function half_month_splits_at_the_midpoint(): void
    {
        // More than half the month is billed in full; half or less, half.
        $this->assertEqualsWithDelta(1.0, ProrationMethod::HalfMonth->units(16, 30), 0.0001);
        $this->assertEqualsWithDelta(0.5, ProrationMethod::HalfMonth->units(15, 30), 0.0001);
        $this->assertEqualsWithDelta(0.5, ProrationMethod::HalfMonth->units(1, 30), 0.0001);
    }

    #[Test]
    public function no_presence_is_never_billed_under_any_rule(): void
    {
        foreach (ProrationMethod::cases() as $method) {
            $this->assertSame(0.0, $method->units(0, 30), $method->value.' billed a student who was not there.');
        }
    }

    #[Test]
    public function presence_beyond_the_month_is_capped_at_one_month(): void
    {
        $this->assertEqualsWithDelta(1.0, ProrationMethod::Daily->units(45, 30), 0.0001);
    }
}
