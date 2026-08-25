<?php

namespace App\Enums;

/**
 * Scope of work §10.3 — whether billing pauses over school holidays and the
 * summer. Set per academic year, not globally.
 */
enum BillingMode: string
{
    case Continue = 'continue';
    case Pause = 'pause';

    public function label(): string
    {
        return match ($this) {
            self::Continue => 'Bill all twelve months',
            self::Pause => 'Pause outside the school year',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $m) => [$m->value => $m->label()])
            ->all();
    }
}
