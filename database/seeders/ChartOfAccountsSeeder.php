<?php

namespace Database\Seeders;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * Scope of work §6 — the required chart of accounts, reproduced exactly.
 *
 * "Additional sub-accounts may be added within each class, but the class
 * numbering must be preserved." Accounts marked system are referenced by the
 * posting engine by code and are protected from renumbering and deletion.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->accounts() as $index => $definition) {
            $code = $definition['code'];
            $type = AccountType::fromCode($code);

            Account::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'name_ar' => $definition['name_ar'] ?? null,
                    'type' => $type,
                    'subtype' => $definition['subtype'] ?? null,
                    'normal_balance' => $definition['normal_balance'] ?? $type->normalBalance(),
                    'class' => ((int) substr($code, 0, 1)) * 1000,
                    'description' => $definition['description'] ?? null,
                    'is_postable' => $definition['is_postable'] ?? true,
                    'is_system' => $definition['is_system'] ?? false,
                    'is_active' => true,
                    'default_nature' => $definition['nature'] ?? null,
                    'requires_department' => $definition['requires_department'] ?? false,
                    'requires_project' => $definition['requires_project'] ?? false,
                    'is_cash_equivalent' => $definition['cash'] ?? false,
                    'sort_order' => $index,
                ]
            );
        }

        $this->assertSystemAccountsExist();
    }

    /**
     * Every code the posting engine refers to must exist, or an automatic
     * journal would post nowhere. Failing here beats failing at month end.
     */
    private function assertSystemAccountsExist(): void
    {
        $missing = collect(config('allva.accounts'))
            ->reject(fn (string $code) => Account::where('code', $code)->exists())
            ->values();

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException(
                'The chart of accounts is missing codes the posting engine needs: '.$missing->implode(', ')
            );
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function accounts(): array
    {
        // Every expense account is flagged so that a posting without a cost
        // centre and a project is rejected — scope of work §7.
        $expense = ['requires_department' => true, 'requires_project' => true];

        return [
            // -- Class 1000 — Assets (§6.1) ------------------------------------
            ['code' => '1010', 'name' => 'Cash on hand', 'name_ar' => 'النقد في الصندوق', 'subtype' => AccountSubtype::CurrentAsset, 'cash' => true, 'is_system' => true],
            ['code' => '1020', 'name' => 'Bank accounts', 'name_ar' => 'الحسابات المصرفية', 'subtype' => AccountSubtype::CurrentAsset, 'cash' => true, 'is_system' => true],
            ['code' => '1030', 'name' => 'Accounts receivable — bus companies', 'name_ar' => 'ذمم مدينة — شركات الحافلات', 'subtype' => AccountSubtype::CurrentAsset, 'is_system' => true],
            ['code' => '1040', 'name' => 'Prepaid expenses', 'name_ar' => 'مصروفات مدفوعة مقدماً', 'subtype' => AccountSubtype::CurrentAsset, 'description' => 'Rent, insurance and licences paid in advance.'],
            ['code' => '1050', 'name' => 'Employee advances', 'name_ar' => 'سلف الموظفين', 'subtype' => AccountSubtype::CurrentAsset, 'is_system' => true],
            ['code' => '1060', 'name' => 'Inventory — devices and spare parts', 'name_ar' => 'المخزون — الأجهزة وقطع الغيار', 'subtype' => AccountSubtype::CurrentAsset, 'description' => 'Trackers, BLE tags, gateways and spare parts held for installation.'],
            ['code' => '1110', 'name' => 'Fixed assets — vehicles', 'name_ar' => 'الأصول الثابتة — المركبات', 'subtype' => AccountSubtype::FixedAsset],
            ['code' => '1120', 'name' => 'Fixed assets — office furniture and equipment', 'name_ar' => 'الأصول الثابتة — الأثاث والمعدات', 'subtype' => AccountSubtype::FixedAsset],
            ['code' => '1130', 'name' => 'Fixed assets — IT equipment and servers', 'name_ar' => 'الأصول الثابتة — أجهزة تقنية المعلومات', 'subtype' => AccountSubtype::FixedAsset],
            ['code' => '1140', 'name' => 'Intangible assets — software and licences', 'name_ar' => 'الأصول غير الملموسة — البرمجيات والتراخيص', 'subtype' => AccountSubtype::IntangibleAsset],
            ['code' => '1190', 'name' => 'Accumulated depreciation', 'name_ar' => 'مجمع الإهلاك', 'subtype' => AccountSubtype::ContraAsset, 'normal_balance' => 'credit', 'is_system' => true],

            // -- Class 2000 — Liabilities (§6.2) -------------------------------
            ['code' => '2010', 'name' => 'Accounts payable — suppliers', 'name_ar' => 'ذمم دائنة — الموردون', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '2020', 'name' => 'Accrued salaries and wages', 'name_ar' => 'رواتب وأجور مستحقة', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '2030', 'name' => 'Payroll taxes and social security payable', 'name_ar' => 'ضرائب ورواتب الضمان الاجتماعي المستحقة', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '2040', 'name' => 'Cyber Gate payable — 50% revenue share', 'name_ar' => 'مستحقات سايبر غيت — حصة الإيراد ٥٠٪', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '2050', 'name' => 'Partner distributions payable', 'name_ar' => 'توزيعات الشركاء المستحقة', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true],
            ['code' => '2060', 'name' => 'Deferred revenue — amounts billed in advance', 'name_ar' => 'إيرادات مؤجلة', 'subtype' => AccountSubtype::CurrentLiability, 'is_system' => true, 'description' => 'Scope of work §4.4 — released to 4010 month by month as the service is delivered.'],
            ['code' => '2070', 'name' => 'Loans and financing', 'name_ar' => 'القروض والتمويل', 'subtype' => AccountSubtype::LongTermLiability],
            ['code' => '2080', 'name' => 'Other accrued liabilities', 'name_ar' => 'التزامات مستحقة أخرى', 'subtype' => AccountSubtype::CurrentLiability],

            // -- Class 3000 — Equity (§6.3) ------------------------------------
            ['code' => '3010', 'name' => 'Partner capital — Partner 1', 'name_ar' => 'رأس مال الشريك ١', 'subtype' => AccountSubtype::PartnerCapital],
            ['code' => '3020', 'name' => 'Partner capital — Partner 2', 'name_ar' => 'رأس مال الشريك ٢', 'subtype' => AccountSubtype::PartnerCapital],
            ['code' => '3030', 'name' => 'Partner capital — Partner 3', 'name_ar' => 'رأس مال الشريك ٣', 'subtype' => AccountSubtype::PartnerCapital],
            ['code' => '3040', 'name' => 'Partner capital — Partner 4', 'name_ar' => 'رأس مال الشريك ٤', 'subtype' => AccountSubtype::PartnerCapital],
            ['code' => '3050', 'name' => 'Partner capital — Partner 5', 'name_ar' => 'رأس مال الشريك ٥', 'subtype' => AccountSubtype::PartnerCapital],
            ['code' => '3060', 'name' => 'Partner current accounts and drawings', 'name_ar' => 'الحسابات الجارية ومسحوبات الشركاء', 'subtype' => AccountSubtype::Equity, 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '3070', 'name' => 'Retained earnings', 'name_ar' => 'الأرباح المحتجزة', 'subtype' => AccountSubtype::Equity, 'is_system' => true],
            ['code' => '3080', 'name' => 'Current year profit', 'name_ar' => 'أرباح السنة الحالية', 'subtype' => AccountSubtype::Equity, 'is_system' => true],

            // -- Class 4000 — Revenue (§6.4) -----------------------------------
            ['code' => '4010', 'name' => 'Student subscription revenue', 'name_ar' => 'إيرادات اشتراكات الطلاب', 'subtype' => AccountSubtype::OperatingRevenue, 'is_system' => true, 'description' => 'Monthly, per the rate table. Scope of work §4.1.'],
            ['code' => '4015', 'name' => 'Late payment fees and penalties', 'name_ar' => 'رسوم التأخير والغرامات', 'subtype' => AccountSubtype::OperatingRevenue, 'is_system' => true],
            ['code' => '4019', 'name' => 'Revenue discounts and adjustments', 'name_ar' => 'خصومات وتسويات الإيرادات', 'subtype' => AccountSubtype::ContraRevenue, 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '4020', 'name' => 'Device and hardware revenue', 'name_ar' => 'إيرادات الأجهزة', 'subtype' => AccountSubtype::OperatingRevenue],
            ['code' => '4030', 'name' => 'Installation and setup fees', 'name_ar' => 'رسوم التركيب والتهيئة', 'subtype' => AccountSubtype::OperatingRevenue],
            ['code' => '4090', 'name' => 'Other operating income', 'name_ar' => 'إيرادات تشغيلية أخرى', 'subtype' => AccountSubtype::OtherIncome],

            // -- Class 5000 — Direct costs (§6.5) ------------------------------
            ['code' => '5010', 'name' => 'Cyber Gate revenue share — 50%', 'name_ar' => 'حصة سايبر غيت من الإيراد ٥٠٪', 'subtype' => AccountSubtype::DirectCost, 'is_system' => true, 'nature' => 'variable', 'requires_project' => true],
            ['code' => '5020', 'name' => 'Hardware and device cost', 'name_ar' => 'تكلفة الأجهزة', 'subtype' => AccountSubtype::DirectCost, 'nature' => 'variable', ...$expense],
            ['code' => '5030', 'name' => 'SIM cards and mobile data', 'name_ar' => 'شرائح الاتصال وبيانات الهاتف', 'subtype' => AccountSubtype::DirectCost, 'nature' => 'variable', ...$expense],
            ['code' => '5040', 'name' => 'Cloud hosting, servers and gateways', 'name_ar' => 'الاستضافة السحابية والخوادم والبوابات', 'subtype' => AccountSubtype::DirectCost, 'nature' => 'fixed', ...$expense],
            ['code' => '5050', 'name' => 'Field installation and technician costs', 'name_ar' => 'تكاليف التركيب الميداني والفنيين', 'subtype' => AccountSubtype::DirectCost, 'nature' => 'variable', ...$expense],
            ['code' => '5060', 'name' => 'Device warranty and replacement', 'name_ar' => 'ضمان واستبدال الأجهزة', 'subtype' => AccountSubtype::DirectCost, 'nature' => 'variable', ...$expense],

            // -- Class 6000 — Payroll (§6.6) -----------------------------------
            ['code' => '6010', 'name' => 'Salaries — management', 'name_ar' => 'الرواتب — الإدارة', 'subtype' => AccountSubtype::Payroll, 'nature' => 'fixed', ...$expense],
            ['code' => '6020', 'name' => 'Salaries — technical and development staff', 'name_ar' => 'الرواتب — الكادر التقني والتطوير', 'subtype' => AccountSubtype::Payroll, 'nature' => 'fixed', ...$expense],
            ['code' => '6030', 'name' => 'Salaries — sales and customer service', 'name_ar' => 'الرواتب — المبيعات وخدمة العملاء', 'subtype' => AccountSubtype::Payroll, 'nature' => 'fixed', ...$expense],
            ['code' => '6040', 'name' => 'Salaries — field and operations staff', 'name_ar' => 'الرواتب — الكادر الميداني والعمليات', 'subtype' => AccountSubtype::Payroll, 'nature' => 'fixed', ...$expense],
            ['code' => '6050', 'name' => 'Bonuses and incentives', 'name_ar' => 'المكافآت والحوافز', 'subtype' => AccountSubtype::Payroll, 'is_system' => true, 'nature' => 'variable', 'requires_department' => true],
            ['code' => '6060', 'name' => 'Overtime', 'name_ar' => 'العمل الإضافي', 'subtype' => AccountSubtype::Payroll, 'is_system' => true, 'nature' => 'variable', 'requires_department' => true],
            ['code' => '6070', 'name' => 'Social security — employer contribution', 'name_ar' => 'الضمان الاجتماعي — حصة صاحب العمل', 'subtype' => AccountSubtype::Payroll, 'is_system' => true, 'nature' => 'fixed', 'requires_department' => true],
            ['code' => '6080', 'name' => 'End-of-service and severance provision', 'name_ar' => 'مخصص نهاية الخدمة', 'subtype' => AccountSubtype::Payroll, 'nature' => 'fixed', ...$expense],
            ['code' => '6090', 'name' => 'Staff transport, meals and allowances', 'name_ar' => 'بدلات النقل والوجبات للموظفين', 'subtype' => AccountSubtype::Payroll, 'is_system' => true, 'nature' => 'variable', 'requires_department' => true],
            ['code' => '6095', 'name' => 'Recruitment and training', 'name_ar' => 'التوظيف والتدريب', 'subtype' => AccountSubtype::Payroll, 'nature' => 'variable', ...$expense],

            // -- Class 7000 — Office and administrative (§6.7) -----------------
            ['code' => '7010', 'name' => 'Office rent', 'name_ar' => 'إيجار المكتب', 'subtype' => AccountSubtype::Administrative, 'nature' => 'fixed', ...$expense],
            ['code' => '7020', 'name' => 'Electricity, water and generator', 'name_ar' => 'الكهرباء والماء والمولدة', 'subtype' => AccountSubtype::Administrative, 'nature' => 'variable', ...$expense],
            ['code' => '7030', 'name' => 'Internet and telephone', 'name_ar' => 'الإنترنت والهاتف', 'subtype' => AccountSubtype::Administrative, 'nature' => 'fixed', ...$expense],
            ['code' => '7040', 'name' => 'Office supplies and stationery', 'name_ar' => 'القرطاسية واللوازم المكتبية', 'subtype' => AccountSubtype::Administrative, 'nature' => 'variable', ...$expense],
            ['code' => '7050', 'name' => 'Cleaning and hospitality', 'name_ar' => 'التنظيف والضيافة', 'subtype' => AccountSubtype::Administrative, 'nature' => 'variable', ...$expense],
            ['code' => '7060', 'name' => 'Office maintenance and repairs', 'name_ar' => 'صيانة وإصلاح المكتب', 'subtype' => AccountSubtype::Administrative, 'nature' => 'variable', ...$expense],
            ['code' => '7070', 'name' => 'Bank charges and transfer fees', 'name_ar' => 'رسوم البنوك والتحويلات', 'subtype' => AccountSubtype::Administrative, 'nature' => 'variable', ...$expense],
            ['code' => '7080', 'name' => 'Legal, audit and accounting fees', 'name_ar' => 'الأتعاب القانونية والتدقيق والمحاسبة', 'subtype' => AccountSubtype::Administrative, 'nature' => 'fixed', ...$expense],
            ['code' => '7090', 'name' => 'Government fees, licences and permits', 'name_ar' => 'الرسوم الحكومية والتراخيص', 'subtype' => AccountSubtype::Administrative, 'nature' => 'fixed', ...$expense],
            ['code' => '7095', 'name' => 'Insurance', 'name_ar' => 'التأمين', 'subtype' => AccountSubtype::Administrative, 'nature' => 'fixed', ...$expense],

            // -- Class 8000 — Vehicle (§6.8) -----------------------------------
            ['code' => '8010', 'name' => 'Fuel', 'name_ar' => 'الوقود', 'subtype' => AccountSubtype::Vehicle, 'nature' => 'variable', ...$expense],
            ['code' => '8020', 'name' => 'Vehicle maintenance and repairs', 'name_ar' => 'صيانة وإصلاح المركبات', 'subtype' => AccountSubtype::Vehicle, 'nature' => 'variable', ...$expense],
            ['code' => '8030', 'name' => 'Vehicle insurance', 'name_ar' => 'تأمين المركبات', 'subtype' => AccountSubtype::Vehicle, 'nature' => 'fixed', ...$expense],
            ['code' => '8040', 'name' => 'Vehicle registration and government fees', 'name_ar' => 'تسجيل المركبات والرسوم الحكومية', 'subtype' => AccountSubtype::Vehicle, 'nature' => 'fixed', ...$expense],
            ['code' => '8050', 'name' => 'Vehicle depreciation', 'name_ar' => 'إهلاك المركبات', 'subtype' => AccountSubtype::Vehicle, 'is_system' => true, 'nature' => 'fixed'],

            // -- Class 9000 — Sales, marketing and other (§6.9) ----------------
            ['code' => '9010', 'name' => 'Advertising and digital marketing', 'name_ar' => 'الإعلان والتسويق الرقمي', 'subtype' => AccountSubtype::SalesMarketing, 'nature' => 'variable', ...$expense],
            ['code' => '9020', 'name' => 'Printing, branding and promotional materials', 'name_ar' => 'الطباعة والهوية والمواد الترويجية', 'subtype' => AccountSubtype::SalesMarketing, 'nature' => 'variable', ...$expense],
            ['code' => '9030', 'name' => 'Travel and business trips', 'name_ar' => 'السفر ورحلات العمل', 'subtype' => AccountSubtype::SalesMarketing, 'nature' => 'variable', ...$expense],
            ['code' => '9040', 'name' => 'Meetings and client entertainment', 'name_ar' => 'الاجتماعات وضيافة العملاء', 'subtype' => AccountSubtype::SalesMarketing, 'nature' => 'variable', ...$expense],
            ['code' => '9050', 'name' => 'Software subscriptions and tools', 'name_ar' => 'اشتراكات البرمجيات والأدوات', 'subtype' => AccountSubtype::SalesMarketing, 'nature' => 'fixed', ...$expense],
            ['code' => '9060', 'name' => 'Depreciation — non-vehicle assets', 'name_ar' => 'الإهلاك — الأصول غير المركبات', 'subtype' => AccountSubtype::SalesMarketing, 'is_system' => true, 'nature' => 'fixed'],
            ['code' => '9070', 'name' => 'Amortisation — software and intangibles', 'name_ar' => 'إطفاء البرمجيات والأصول غير الملموسة', 'subtype' => AccountSubtype::SalesMarketing, 'is_system' => true, 'nature' => 'fixed'],
            ['code' => '9080', 'name' => 'Bad debt written off', 'name_ar' => 'الديون المعدومة', 'subtype' => AccountSubtype::SalesMarketing, 'is_system' => true, 'nature' => 'variable', 'requires_department' => true],
            ['code' => '9090', 'name' => 'Miscellaneous expenses', 'name_ar' => 'مصروفات متنوعة', 'subtype' => AccountSubtype::SalesMarketing, 'is_system' => true, 'nature' => 'variable'],
        ];
    }
}
