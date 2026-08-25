<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Closed periods reject new postings. Locked additionally rejects reopening
     * — used once a year has been audited.
     */
    public function acceptsPostings(): bool
    {
        return $this === self::Open;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'badge-success',
            self::Closed => 'badge-neutral',
            self::Locked => 'badge-danger',
        };
    }
}
