<?php

namespace App\Enums;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Only posted journals move the ledger. Draft and reversed do not. */
    public function affectsLedger(): bool
    {
        return $this === self::Posted;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-neutral',
            self::Posted => 'badge-success',
            self::Reversed => 'badge-danger',
        };
    }
}
