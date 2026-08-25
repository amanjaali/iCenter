<?php

namespace App\Enums;

/**
 * Expense classes carry meaning beyond "expense": §7 requires direct costs
 * (class 5000) to be reported separately from operating expenses (6000-9000),
 * and the income statement is grouped by these bands.
 */
enum AccountSubtype: string
{
    case CurrentAsset = 'current_asset';
    case FixedAsset = 'fixed_asset';
    case IntangibleAsset = 'intangible_asset';
    case ContraAsset = 'contra_asset';
    case CurrentLiability = 'current_liability';
    case LongTermLiability = 'long_term_liability';
    case PartnerCapital = 'partner_capital';
    case Equity = 'equity';
    case OperatingRevenue = 'operating_revenue';
    case ContraRevenue = 'contra_revenue';
    case OtherIncome = 'other_income';
    case DirectCost = 'direct_cost';
    case Payroll = 'payroll';
    case Administrative = 'administrative';
    case Vehicle = 'vehicle';
    case SalesMarketing = 'sales_marketing';

    public function label(): string
    {
        return match ($this) {
            self::CurrentAsset => 'Current asset',
            self::FixedAsset => 'Fixed asset',
            self::IntangibleAsset => 'Intangible asset',
            self::ContraAsset => 'Contra asset',
            self::CurrentLiability => 'Current liability',
            self::LongTermLiability => 'Long-term liability',
            self::PartnerCapital => 'Partner capital',
            self::Equity => 'Equity',
            self::OperatingRevenue => 'Operating revenue',
            self::ContraRevenue => 'Contra revenue',
            self::OtherIncome => 'Other income',
            self::DirectCost => 'Direct cost',
            self::Payroll => 'Payroll expense',
            self::Administrative => 'Office and administrative',
            self::Vehicle => 'Vehicle expense',
            self::SalesMarketing => 'Sales, marketing and other',
        };
    }
}
