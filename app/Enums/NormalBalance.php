<?php

namespace App\Enums;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    /** +1 when a debit increases the account, -1 when it decreases it. */
    public function sign(): int
    {
        return $this === self::Debit ? 1 : -1;
    }
}
