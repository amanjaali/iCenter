<?php

/*
|--------------------------------------------------------------------------
| ALLVA Accounting — static configuration
|--------------------------------------------------------------------------
|
| Values here are *bootstrap defaults*. Anything an accountant or an
| administrator is allowed to change at runtime lives in the `settings` table
| and is read through App\Services\SettingsService. Nothing that affects a
| monetary calculation (rates, split percentages, ownership) is ever read
| straight from this file — see the scope of work, §4.1: "It must never be
| hardcoded."
|
*/

return [

    'currency' => [
        'code' => env('ALLVA_CURRENCY', 'IQD'),
        'symbol' => 'IQD',
        // IQD is conventionally quoted without minor units, but the ledger
        // stores 2 decimal places so that prorated fractions never silently
        // vanish. Presentation rounds; storage does not.
        'display_decimals' => 0,
        'storage_decimals' => 2,
    ],

    'fiscal_year_start' => env('ALLVA_FISCAL_YEAR_START', '01-01'),

    /*
    | Account codes the engine posts to by name. Changing a code here changes
    | where the automatic journals land, so these are mirrored by a seeder
    | assertion — a missing code fails loudly rather than posting nowhere.
    */
    'accounts' => [
        'cash' => '1010',
        'bank' => '1020',
        'receivable' => '1030',
        'prepaid' => '1040',
        'employee_advances' => '1050',
        'inventory' => '1060',
        'accumulated_depreciation' => '1190',

        'payable' => '2010',
        'accrued_salaries' => '2020',
        'payroll_taxes' => '2030',
        'revenue_share_payable' => '2040',
        'partner_distributions_payable' => '2050',
        'deferred_revenue' => '2060',

        'partner_current' => '3060',
        'retained_earnings' => '3070',
        'current_year_profit' => '3080',

        'subscription_revenue' => '4010',
        'late_fees' => '4015',
        'revenue_discounts' => '4019',

        'revenue_share_cost' => '5010',

        'vehicle_depreciation' => '8050',
        'depreciation_other' => '9060',
        'amortisation' => '9070',
        'bad_debt' => '9080',
    ],

    /*
    | Document number formats. {seq} is zero padded to `pad`.
    */
    'numbering' => [
        'invoice' => ['prefix' => 'INV-{year}-', 'pad' => 5],
        'payment' => ['prefix' => 'RCP-{year}-', 'pad' => 5],
        'expense' => ['prefix' => 'EXP-{year}-', 'pad' => 5],
        'journal' => ['prefix' => 'JV-{year}-', 'pad' => 5],
        'payroll' => ['prefix' => 'PR-{year}-', 'pad' => 4],
        'distribution' => ['prefix' => 'DIST-{year}-', 'pad' => 4],
        'revenue_share' => ['prefix' => 'RS-{year}-', 'pad' => 4],
        'depreciation' => ['prefix' => 'DEP-{year}-', 'pad' => 4],
    ],

    /*
    | Defaults written into the settings table by SettingsSeeder. The three
    | open decisions from §10 of the scope of work are all here, each one a
    | switch rather than a hardcoded rule, so that whichever way the client
    | decides the system does not need rebuilding.
    */
    'settings_defaults' => [
        // §10.1 — 'gross': Cyber Gate takes 50% of gross revenue and is booked
        // as a direct cost (5010); expenses then come out of ALLVA's half.
        // 'net': operating expenses are deducted first and the remaining net
        // profit is split 50/50. The scope of work assumes 'gross'.
        'revenue_share.basis' => 'gross',
        'revenue_share.partner_name' => 'Cyber Gate',
        'revenue_share.percent' => '50',

        // §10.2 — how a student who joins or leaves mid-month is billed.
        // daily | full_month | half_month
        'billing.proration_method' => 'daily',
        'billing.invoice_due_days' => '15',
        'billing.generate_on' => 'month_end',

        // §10.3 — set per academic year on the academic year record itself;
        // this is only the default applied to a newly created year.
        'billing.default_billing_mode' => 'continue',

        // §7 — how overhead that cannot be attributed to one project is
        // spread across projects in the profitability report.
        // revenue | equal | headcount | none
        'allocation.overhead_method' => 'revenue',

        'company.name' => 'ALLVA Company',
        'company.legal_name' => 'ALLVA Company for Technology',
        'company.tax_number' => '',
        'company.address' => 'Baghdad, Iraq',
        'company.phone' => '',
        'company.email' => 'accounting@allva.iq',
    ],
];
