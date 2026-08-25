<?php

namespace App\Enums;

/**
 * Scope of work §10.2 — the open decision on students who join or leave part
 * way through a month. One rule is chosen in Settings and applied to every bus
 * company; the rule in force is frozen onto each invoice so a historical
 * invoice can always be reproduced.
 */
enum ProrationMethod: string
{
    case Daily = 'daily';
    case FullMonth = 'full_month';
    case HalfMonth = 'half_month';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Pro-rate by day',
            self::FullMonth => 'Charge a full month',
            self::HalfMonth => 'Full or half month',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Daily => 'A student present for part of a month is billed billable days ÷ days in month.',
            self::FullMonth => 'Any student present for at least one day in the month is billed the full monthly rate.',
            self::HalfMonth => 'A student present for more than half the month is billed in full; a student present for half or less is billed half.',
        };
    }

    /**
     * Convert days of presence into billable student-months.
     *
     * @param  int  $billableDays  days the student was enrolled within the month
     * @param  int  $daysInMonth   calendar length of the month
     */
    public function units(int $billableDays, int $daysInMonth): float
    {
        if ($billableDays <= 0 || $daysInMonth <= 0) {
            return 0.0;
        }

        return match ($this) {
            self::Daily => round(min($billableDays, $daysInMonth) / $daysInMonth, 6),
            self::FullMonth => 1.0,
            self::HalfMonth => $billableDays > ($daysInMonth / 2) ? 1.0 : 0.5,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $m) => [$m->value => $m->label()])
            ->all();
    }
}
