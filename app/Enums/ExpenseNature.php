<?php

namespace App\Enums;

/** Scope of work §7 — fixed against variable expenses must be reportable. */
enum ExpenseNature: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public static function options(): array
    {
        return ['fixed' => 'Fixed', 'variable' => 'Variable'];
    }
}
