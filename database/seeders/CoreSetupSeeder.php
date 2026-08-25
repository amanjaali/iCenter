<?php

namespace Database\Seeders;

use App\Enums\BillingMode;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Department;
use App\Models\Partner;
use App\Models\Project;
use App\Models\RateCard;
use App\Models\RateChangeLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Accounting\PeriodService;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Everything the system needs to be usable on day one: settings, cost centres,
 * projects, the five partners, the 2026-2027 academic year and its 4,000 IQD
 * rate, the fiscal calendar, and one login per role.
 */
class CoreSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->departments();
        $this->projects();
        $this->partners();
        $this->users();
        $this->fiscalCalendar();
        $this->academicYearAndRate();
    }

    private function settings(): void
    {
        $definitions = [
            // §10.1 — the open decision on where the Cyber Gate split is taken.
            'revenue_share.basis' => ['group' => 'revenue_share', 'cast' => 'string', 'label' => 'Revenue share basis', 'description' => 'Scope of work §10.1. Whether Cyber Gate takes 50% of gross revenue (expenses then deducted from ALLVA’s half) or 50% of net profit after expenses.'],
            'revenue_share.partner_name' => ['group' => 'revenue_share', 'cast' => 'string', 'label' => 'Revenue share partner'],
            'revenue_share.percent' => ['group' => 'revenue_share', 'cast' => 'decimal', 'label' => 'Revenue share percentage'],

            // §10.2 — the mid-month join and leave rule.
            'billing.proration_method' => ['group' => 'billing', 'cast' => 'string', 'label' => 'Mid-month rule', 'description' => 'Scope of work §10.2. Applied consistently to every bus company and frozen onto each invoice.'],
            'billing.invoice_due_days' => ['group' => 'billing', 'cast' => 'int', 'label' => 'Default payment terms (days)'],
            'billing.generate_on' => ['group' => 'billing', 'cast' => 'string', 'label' => 'Invoice generation point'],
            'billing.default_billing_mode' => ['group' => 'billing', 'cast' => 'string', 'label' => 'Default holiday billing rule', 'description' => 'Scope of work §10.3. Set per academic year; this is only the default for a new year.'],

            // §7 — overhead allocation.
            'allocation.overhead_method' => ['group' => 'allocation', 'cast' => 'string', 'label' => 'Overhead allocation method', 'description' => 'Scope of work §7. How overhead that cannot be attributed to one project is spread so project profitability stays meaningful.'],

            'company.name' => ['group' => 'company', 'cast' => 'string', 'label' => 'Company name'],
            'company.legal_name' => ['group' => 'company', 'cast' => 'string', 'label' => 'Legal name'],
            'company.tax_number' => ['group' => 'company', 'cast' => 'string', 'label' => 'Tax number'],
            'company.address' => ['group' => 'company', 'cast' => 'string', 'label' => 'Address'],
            'company.phone' => ['group' => 'company', 'cast' => 'string', 'label' => 'Phone'],
            'company.email' => ['group' => 'company', 'cast' => 'string', 'label' => 'Email'],
        ];

        foreach ($definitions as $key => $meta) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    // Read from the flat defaults array by its literal dotted
                    // key — config() would treat the dots as nesting.
                    'value' => (string) (SettingsService::configDefault($key) ?? ''),
                    'group' => $meta['group'],
                    'cast' => $meta['cast'],
                    'label' => $meta['label'],
                    'description' => $meta['description'] ?? null,
                ]
            );
        }
    }

    private function departments(): void
    {
        // Scope of work §7 — the five cost centres named in the specification.
        $departments = [
            ['code' => 'MGT', 'name' => 'Management', 'name_ar' => 'الإدارة', 'headcount_weight' => 3],
            ['code' => 'TEC', 'name' => 'Technical', 'name_ar' => 'التقني', 'headcount_weight' => 8],
            ['code' => 'SAL', 'name' => 'Sales', 'name_ar' => 'المبيعات', 'headcount_weight' => 4],
            ['code' => 'OPS', 'name' => 'Operations', 'name_ar' => 'العمليات', 'headcount_weight' => 6],
            ['code' => 'ADM', 'name' => 'Administration', 'name_ar' => 'الشؤون الإدارية', 'headcount_weight' => 2],
        ];

        foreach ($departments as $index => $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department + ['sort_order' => $index, 'is_active' => true]
            );
        }
    }

    private function projects(): void
    {
        Project::updateOrCreate(
            ['code' => 'ETRK'],
            [
                'name' => 'eTrackify',
                'name_ar' => 'إي-تراكيفاي',
                'description' => 'School bus student tracking. Revenue is shared 50/50 with Cyber Gate.',
                'is_default' => true,
                'is_overhead_pool' => false,
                'receives_overhead' => true,
                'started_on' => Carbon::create(2026, 9, 1),
                'is_active' => true,
            ]
        );

        // Company overhead is booked here when it belongs to no single project;
        // the profitability report spreads it by the configured method (§7).
        Project::updateOrCreate(
            ['code' => 'CORP'],
            [
                'name' => 'Company overhead',
                'name_ar' => 'المصروفات العامة للشركة',
                'description' => 'Costs that belong to ALLVA as a whole rather than to one project.',
                'is_default' => false,
                'is_overhead_pool' => true,
                'receives_overhead' => false,
                'is_active' => true,
            ]
        );
    }

    private function partners(): void
    {
        // Scope of work §2 — five partners, 20% each. The percentage is data,
        // not a constant, so a change in shareholding needs no code change.
        $capitalCodes = ['3010', '3020', '3030', '3040', '3050'];

        for ($i = 1; $i <= 5; $i++) {
            Partner::updateOrCreate(
                ['code' => 'P'.$i],
                [
                    'name' => 'Partner '.$i,
                    'ownership_percent' => 20.0000,
                    'capital_account_id' => Account::where('code', $capitalCodes[$i - 1])->value('id'),
                    'joined_on' => Carbon::create(2026, 1, 1),
                    'is_active' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }

    private function users(): void
    {
        // Scope of work §9.2 — one login per role, so the permission model can
        // be exercised the moment the system is installed.
        $users = [
            ['name' => 'System Administrator', 'email' => 'admin@allva.iq', 'role' => UserRole::Administrator, 'job_title' => 'Administrator'],
            ['name' => 'Company Accountant', 'email' => 'accountant@allva.iq', 'role' => UserRole::Accountant, 'job_title' => 'Accountant'],
            ['name' => 'Operations Officer', 'email' => 'operations@allva.iq', 'role' => UserRole::Operations, 'job_title' => 'Registry and operations'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user + ['password' => Hash::make('password'), 'is_active' => true]
            );
        }

        // §9.1 — each of the five partners has their own read-only login.
        foreach (Partner::orderBy('sort_order')->get() as $partner) {
            $email = strtolower(str_replace(' ', '', $partner->code)).'@allva.iq';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $partner->name,
                    'role' => UserRole::Partner,
                    'partner_id' => $partner->id,
                    'job_title' => 'Partner — '.number_format((float) $partner->ownership_percent, 0).'%',
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            $partner->update(['user_id' => $user->id, 'email' => $email]);
        }
    }

    private function fiscalCalendar(): void
    {
        // The academic year 2026-2027 spans two calendar years, so both need a
        // fiscal calendar before anything can be posted.
        $periods = app(PeriodService::class);

        foreach ([2026, 2027] as $year) {
            $periods->createFiscalYear($year);
        }
    }

    private function academicYearAndRate(): void
    {
        // Scope of work §4.1 — 4,000 IQD per student per month for 2026-2027.
        $year = AcademicYear::updateOrCreate(
            ['name' => '2026-2027'],
            [
                'start_date' => Carbon::create(2026, 9, 1),
                'end_date' => Carbon::create(2027, 8, 31),
                // §10.3 — the default; change it per year on the Academic years
                // screen once the client confirms the holiday rule.
                'billing_mode' => BillingMode::Continue,
                'billable_months' => [9, 10, 11, 12, 1, 2, 3, 4, 5, 6],
                'is_active' => true,
                'notes' => 'Scope of work §4.1 — the applicable period for the 4,000 IQD rate.',
            ]
        );

        $rate = RateCard::firstOrCreate(
            [
                'academic_year_id' => $year->id,
                'bus_company_id' => null,
                'effective_from' => $year->start_date,
            ],
            [
                'amount' => 4000.00,
                'period' => 'per_student_month',
                'effective_to' => $year->end_date,
                'is_active' => true,
                'notes' => 'Standard rate for all bus companies — scope of work §4.1.',
            ]
        );

        // §4.2 — every rate change is logged with a reason, including the first.
        RateChangeLog::firstOrCreate(
            ['rate_card_id' => $rate->id, 'action' => 'created'],
            [
                'user_name' => 'System',
                'new_amount' => 4000.00,
                'new_values' => ['amount' => 4000.00, 'academic_year' => $year->name],
                'reason' => 'Initial rate for the 2026–2027 academic year, per the scope of work §4.1.',
            ]
        );
    }
}
