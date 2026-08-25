<?php

namespace App\Enums;

/**
 * Scope of work §7 — capital expenditure is capitalised as a fixed asset and
 * depreciated; operating expenditure is charged in the period incurred.
 */
enum CapitalTreatment: string
{
    case Capex = 'capex';
    case Opex = 'opex';

    public function label(): string
    {
        return match ($this) {
            self::Capex => 'Capital expenditure',
            self::Opex => 'Operating expenditure',
        };
    }

    public static function options(): array
    {
        return ['opex' => 'Operating expenditure', 'capex' => 'Capital expenditure'];
    }
}
