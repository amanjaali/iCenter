<?php

namespace App\Enums;

/**
 * Scope of work §10.1 — the open decision that "changes every partner's
 * figure". Both treatments are implemented; the setting chooses which one
 * runs, and the choice is stamped on every revenue share run so a posted
 * period is never restated by a later change of mind.
 */
enum RevenueShareBasis: string
{
    /**
     * Cyber Gate takes 50% of *gross* revenue. The share is a direct cost
     * (5010) and ALLVA's operating expenses come out of its own half.
     * This is what the scope of work document assumes.
     */
    case Gross = 'gross';

    /**
     * Operating expenses are deducted first and the remaining *net* profit is
     * split 50/50.
     */
    case Net = 'net';

    public function label(): string
    {
        return match ($this) {
            self::Gross => 'Share of gross revenue',
            self::Net => 'Share of net profit',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Gross => 'Cyber Gate receives 50% of gross revenue, booked as a direct cost (5010). Operating expenses are then deducted from ALLVA’s 50%.',
            self::Net => 'Operating expenses are deducted from revenue first, and the remaining net profit is split 50/50 with Cyber Gate.',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $b) => [$b->value => $b->label()])
            ->all();
    }
}
