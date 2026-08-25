<?php

namespace App\Enums;

enum JournalSource: string
{
    case Manual = 'manual';
    case Invoice = 'invoice';
    case RevenueRecognition = 'revenue_recognition';
    case Payment = 'payment';
    case Expense = 'expense';
    case SupplierPayment = 'supplier_payment';
    case Payroll = 'payroll';
    case PayrollPayment = 'payroll_payment';
    case Depreciation = 'depreciation';
    case RevenueShare = 'revenue_share';
    case RevenueSharePayment = 'revenue_share_payment';
    case Distribution = 'distribution';
    case PartnerPayout = 'partner_payout';
    case AssetDisposal = 'asset_disposal';
    case Opening = 'opening';
    case Closing = 'closing';
    case Reversal = 'reversal';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** Automatic journals are owned by their source document, not by the user. */
    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }
}
