<?php

namespace App\Enums;

/**
 * Scope of work §7 — "Where an overhead cannot be attributed to a single
 * project, the system must apply a defined allocation method so that eTrackify
 * project profitability remains meaningful."
 *
 * Allocation happens at reporting time, not by posting extra journals: the
 * ledger keeps overhead where it was incurred, and the project profitability
 * report spreads it.
 */
enum OverheadAllocationMethod: string
{
    case Revenue = 'revenue';
    case Equal = 'equal';
    case Headcount = 'headcount';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Revenue => 'In proportion to project revenue',
            self::Equal => 'Split equally between projects',
            self::Headcount => 'In proportion to department headcount',
            self::None => 'Do not allocate overhead',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $m) => [$m->value => $m->label()])
            ->all();
    }
}
