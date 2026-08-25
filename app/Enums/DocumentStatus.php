<?php

namespace App\Enums;

/**
 * The shared lifecycle used by invoices, expenses, payroll runs, revenue share
 * runs and distribution runs. A document is editable while it is draft; once it
 * is posted it has hit the ledger and may only be reversed.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Approved = 'approved';
    case Posted = 'posted';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Settled = 'settled';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::PartiallyPaid => 'Partly paid',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Tailwind classes for the status badge component. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-neutral',
            self::Issued, self::Approved, self::Posted => 'badge-info',
            self::PartiallyPaid => 'badge-warning',
            self::Paid, self::Settled => 'badge-success',
            self::Void => 'badge-danger',
        };
    }
}
