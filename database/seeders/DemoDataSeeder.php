<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Bus;
use App\Models\BusCompany;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Supplier;
use App\Services\Accounting\PeriodService;
use App\Services\Assets\DepreciationService;
use App\Services\AuditLogger;
use App\Services\Billing\BillingService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentService;
use App\Services\Billing\RevenueRecognitionService;
use App\Services\Expenses\ExpenseService;
use App\Services\Partners\DistributionService;
use App\Services\Partners\RevenueShareService;
use App\Services\Payroll\PayrollService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A worked example of the whole cycle: registry, billing, collection, payroll,
 * expenses, depreciation, the Cyber Gate share and a partner distribution.
 *
 * Useful for demonstrating the system and for checking that the reports agree
 * with each other on real data. Not part of the installation seed.
 */
class DemoDataSeeder extends Seeder
{
    private Carbon $start;

    public function run(): void
    {
        /*
         * The demo runs the four months ending with the current one, so the
         * dashboard and the reports have something to show today rather than
         * plotting a year that has not happened yet. The 2026-2027 academic
         * year from the scope of work is seeded separately and left ready for
         * when it starts.
         */
        $this->start = Carbon::today()->startOfMonth()->subMonths(3);

        foreach ([$this->start->year, $this->start->copy()->addMonths(3)->year] as $year) {
            app(PeriodService::class)->createFiscalYear($year);
        }

        $this->academicYearForDemoPeriod();

        $this->command?->info('  Registry…');
        $companies = $this->registry();

        $this->command?->info('  Staff and suppliers…');
        $this->staffAndSuppliers();

        $this->command?->info('  Opening capital…');
        $this->openingCapital();

        // Four months of trading: Sep, Oct, Nov, Dec 2026.
        foreach ([0, 1, 2, 3] as $offset) {
            $month = $this->start->copy()->addMonths($offset);
            $this->command?->info('  '.$month->format('F Y').'…');

            $this->billMonth($month, $companies);
            $this->payrollMonth($month);
            $this->expensesFor($month);
            $this->depreciationFor($month);
            $this->revenueShareFor($month);
        }

        $this->command?->info('  Partner distribution…');
        $this->distribution();
    }

    /**
     * The academic year covering the demo window, on the usual September to
     * August convention, with the same 4,000 IQD rate the scope of work sets
     * for 2026-2027. Without it nothing is billable: §4.2 refuses to invent a
     * rate for a month no rate card covers.
     *
     * Because the demo runs in the months before 2026-2027 begins, this
     * creates the preceding year — which also gives the academic year
     * comparison report (§8.2) a second year to compare against.
     */
    private function academicYearForDemoPeriod(): void
    {
        if (\App\Models\AcademicYear::forDate($this->start)) {
            return;
        }

        // September starts the school year; anything earlier belongs to the
        // year that began the previous September.
        $openingYear = $this->start->month >= 9 ? $this->start->year : $this->start->year - 1;
        $yearStart = Carbon::create($openingYear, 9, 1)->startOfDay();
        $yearEnd = Carbon::create($openingYear + 1, 8, 31)->endOfDay();

        $year = \App\Models\AcademicYear::updateOrCreate(
            ['name' => $openingYear.'-'.($openingYear + 1)],
            [
                'start_date' => $yearStart,
                'end_date' => $yearEnd,
                // 'continue' so the summer months in the demo window are billed
                // — which is also what demonstrates the §10.3 setting.
                'billing_mode' => \App\Enums\BillingMode::Continue,
                'billable_months' => [9, 10, 11, 12, 1, 2, 3, 4, 5, 6],
                'is_active' => true,
                'notes' => 'Preceding academic year, carrying the same rate as 2026-2027.',
            ]
        );

        \App\Models\RateCard::firstOrCreate(
            [
                'academic_year_id' => $year->id,
                'bus_company_id' => null,
                'effective_from' => $yearStart->toDateString(),
            ],
            [
                'amount' => 4000.00,
                'period' => 'per_student_month',
                'effective_to' => $yearEnd->toDateString(),
                'is_active' => true,
                'notes' => 'Standard rate for all bus companies — scope of work §4.1.',
            ]
        );

        // Only one year is current at a time; the 2026-2027 year set up from
        // the scope of work stays configured and takes over when it starts.
        \App\Models\AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
    }

    /** @return array<int,BusCompany> */
    private function registry(): array
    {
        // Sized to a real city-wide deployment: at 4,000 IQD per student the
        // service only carries its cost base at several thousand students, so
        // a smaller demo would show a loss and the distribution flow would have
        // nothing to distribute.
        $definitions = [
            ['code' => 'BC-001', 'name' => 'Al Rasheed Transport', 'contact' => 'Hassan Ali', 'buses' => 150, 'students' => 36],
            ['code' => 'BC-002', 'name' => 'Baghdad School Lines', 'contact' => 'Noor Kadhim', 'buses' => 120, 'students' => 34],
            ['code' => 'BC-003', 'name' => 'Tigris Student Transport', 'contact' => 'Omar Salim', 'buses' => 90, 'students' => 32],
        ];

        $companies = [];
        $studentSequence = 1;
        $now = now();

        foreach ($definitions as $definition) {
            $company = BusCompany::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'contact_name' => $definition['contact'],
                    'phone' => '+964 770 '.random_int(1000000, 9999999),
                    'email' => strtolower(str_replace(' ', '.', $definition['contact'])).'@example.iq',
                    'address' => 'Baghdad, Iraq',
                    'payment_terms_days' => 15,
                    'contract_start' => $this->start->copy(),
                    'status' => 'active',
                ]
            );

            $busRows = [];

            for ($b = 1; $b <= $definition['buses']; $b++) {
                $busRows[] = [
                    'bus_company_id' => $company->id,
                    'code' => $company->code.'-BUS-'.str_pad((string) $b, 3, '0', STR_PAD_LEFT),
                    'plate_number' => 'BG '.random_int(10000, 99999),
                    'capacity' => 40,
                    'driver_name' => 'Driver '.$b,
                    'driver_phone' => '+964 771 '.random_int(1000000, 9999999),
                    'route_name' => 'Route '.$b,
                    'device_serial' => 'ETK-'.random_int(100000, 999999),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($busRows, 200) as $chunk) {
                Bus::insert($chunk);
            }

            $buses = Bus::where('bus_company_id', $company->id)->orderBy('id')->get();

            $studentRows = [];
            $plan = [];

            foreach ($buses as $bus) {
                for ($s = 0; $s < $definition['students']; $s++) {
                    // Most students start with the academic year; a fraction
                    // join or leave mid-month so the proration rule from §10.2
                    // is genuinely exercised rather than assumed.
                    $joinOffset = $studentSequence % 17 === 0 ? random_int(8, 20) : 0;
                    $joinedOn = $this->start->copy()->addDays($joinOffset);
                    $leavesOn = $studentSequence % 23 === 0
                        ? $this->start->copy()->addMonths(2)->addDays(12)
                        : null;

                    $code = 'STU-'.str_pad((string) $studentSequence, 6, '0', STR_PAD_LEFT);

                    $studentRows[] = [
                        'bus_company_id' => $company->id,
                        'bus_id' => $bus->id,
                        'code' => $code,
                        'name' => 'Student '.$studentSequence,
                        'guardian_name' => 'Guardian '.$studentSequence,
                        'guardian_phone' => '+964 750 '.random_int(1000000, 9999999),
                        'school_name' => 'School '.(($studentSequence % 24) + 1),
                        'grade' => 'Grade '.(($studentSequence % 9) + 1),
                        'joined_on' => $joinedOn->toDateString(),
                        'left_on' => $leavesOn?->toDateString(),
                        'status' => $leavesOn ? 'left' : 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $plan[$code] = [
                        'bus_id' => $bus->id,
                        'start' => $joinedOn->toDateString(),
                        'end' => $leavesOn?->toDateString(),
                    ];

                    $studentSequence++;
                }
            }

            foreach (array_chunk($studentRows, 500) as $chunk) {
                Student::insert($chunk);
            }

            // The enrollment history is what billing actually counts, so it is
            // written from the same plan rather than derived afterwards.
            $enrollmentRows = [];

            Student::where('bus_company_id', $company->id)
                ->select('id', 'code')
                ->orderBy('id')
                ->chunk(1000, function ($chunk) use (&$enrollmentRows, $plan, $company, $now) {
                    foreach ($chunk as $student) {
                        $entry = $plan[$student->code] ?? null;

                        if (! $entry) {
                            continue;
                        }

                        $enrollmentRows[] = [
                            'student_id' => $student->id,
                            'bus_id' => $entry['bus_id'],
                            'bus_company_id' => $company->id,
                            'start_date' => $entry['start'],
                            'end_date' => $entry['end'],
                            'start_reason' => 'enrolled',
                            'end_reason' => $entry['end'] ? 'left' : null,
                            'is_billable' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                });

            foreach (array_chunk($enrollmentRows, 500) as $chunk) {
                StudentEnrollment::insert($chunk);
            }

            $companies[] = $company;
        }

        return $companies;
    }

    private function staffAndSuppliers(): void
    {
        $staff = [
            ['code' => 'EMP-001', 'name' => 'Rand Ahmed', 'dept' => 'MGT', 'account' => '6010', 'position' => 'General Manager', 'salary' => 1_500_000],
            ['code' => 'EMP-002', 'name' => 'Yusuf Kareem', 'dept' => 'MGT', 'account' => '6010', 'position' => 'Finance Manager', 'salary' => 1_100_000],
            ['code' => 'EMP-003', 'name' => 'Zainab Hadi', 'dept' => 'TEC', 'account' => '6020', 'position' => 'Lead Developer', 'salary' => 1_000_000],
            ['code' => 'EMP-004', 'name' => 'Mustafa Jabbar', 'dept' => 'TEC', 'account' => '6020', 'position' => 'Backend Developer', 'salary' => 750_000],
            ['code' => 'EMP-005', 'name' => 'Dina Salman', 'dept' => 'TEC', 'account' => '6020', 'position' => 'Mobile Developer', 'salary' => 700_000],
            ['code' => 'EMP-006', 'name' => 'Ali Hussein', 'dept' => 'SAL', 'account' => '6030', 'position' => 'Sales Lead', 'salary' => 700_000],
            ['code' => 'EMP-007', 'name' => 'Sara Naji', 'dept' => 'SAL', 'account' => '6030', 'position' => 'Customer Service', 'salary' => 450_000],
            ['code' => 'EMP-008', 'name' => 'Karrar Abbas', 'dept' => 'OPS', 'account' => '6040', 'position' => 'Field Supervisor', 'salary' => 550_000],
            ['code' => 'EMP-009', 'name' => 'Hayder Mohammed', 'dept' => 'OPS', 'account' => '6040', 'position' => 'Field Technician', 'salary' => 450_000],
            ['code' => 'EMP-010', 'name' => 'Israa Fadhil', 'dept' => 'ADM', 'account' => '6010', 'position' => 'Office Administrator', 'salary' => 400_000],
            ['code' => 'EMP-011', 'name' => 'Ahmed Salih', 'dept' => 'OPS', 'account' => '6040', 'position' => 'Field Technician', 'salary' => 450_000],
            ['code' => 'EMP-012', 'name' => 'Rusul Adnan', 'dept' => 'SAL', 'account' => '6030', 'position' => 'Customer Service', 'salary' => 420_000],
        ];

        $project = Project::default();

        foreach ($staff as $member) {
            Employee::updateOrCreate(
                ['code' => $member['code']],
                [
                    'name' => $member['name'],
                    'department_id' => Department::where('code', $member['dept'])->value('id'),
                    'project_id' => in_array($member['dept'], ['TEC', 'OPS'], true) ? $project?->id : null,
                    'salary_account_id' => Account::byCode($member['account'])->id,
                    'position' => $member['position'],
                    'hire_date' => $this->start->copy()->subMonths(2),
                    'base_salary' => $member['salary'],
                    'allowances' => round($member['salary'] * 0.05),
                    'employee_ss_rate' => 5,
                    'employer_ss_rate' => 12,
                    'status' => 'active',
                ]
            );
        }

        foreach ([
            ['code' => 'SUP-001', 'name' => 'Zain Iraq', 'terms' => 0],
            ['code' => 'SUP-002', 'name' => 'Baghdad Cloud Services', 'terms' => 30],
            ['code' => 'SUP-003', 'name' => 'Karrada Office Tower', 'terms' => 0],
            ['code' => 'SUP-004', 'name' => 'Gulf Device Imports', 'terms' => 45],
        ] as $supplier) {
            Supplier::updateOrCreate(
                ['code' => $supplier['code']],
                ['name' => $supplier['name'], 'payment_terms_days' => $supplier['terms'], 'is_active' => true]
            );
        }
    }

    /** Partners fund the company so there is cash to trade with. */
    private function openingCapital(): void
    {
        $journals = app(\App\Services\Accounting\JournalService::class);
        $capitalPerPartner = 25_000_000;

        $lines = [[
            'account' => Account::system('bank'),
            'description' => 'Opening capital contributed by the partners',
            'debit' => $capitalPerPartner * 5,
        ]];

        foreach (['3010', '3020', '3030', '3040', '3050'] as $index => $code) {
            $lines[] = [
                'account' => $code,
                'description' => 'Capital contributed — Partner '.($index + 1),
                'credit' => $capitalPerPartner,
            ];
        }

        $journals->post(
            $this->start->copy()->subDays(1),
            'Opening capital contributions',
            $lines,
            \App\Enums\JournalSource::Opening,
        );
    }

    /** @param array<int,BusCompany> $companies */
    private function billMonth(Carbon $month, array $companies): void
    {
        $billing = app(BillingService::class);
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);

        $result = $billing->generateMonth($month);

        foreach ($result['created'] as $invoice) {
            $invoices->issue($invoice);
        }

        app(RevenueRecognitionService::class)->recogniseDue($month->copy()->endOfMonth());

        // Collections: most companies pay in full, one pays late and partially,
        // so the ageing and collection reports have something to show.
        foreach ($result['created'] as $index => $invoice) {
            $invoice->refresh();

            $monthOffset = (int) $this->start->diffInMonths($month);

            // The third company stops paying in the last two months, and the
            // second underpays once, so the ageing and collection reports have
            // real arrears to show.
            if ($invoice->bus_company_id === $companies[2]->id && $monthOffset >= 2) {
                continue;
            }

            $fraction = $invoice->bus_company_id === $companies[1]->id && $monthOffset === 1 ? 0.6 : 1.0;
            $amount = min(
                \App\Support\Money::whole((float) $invoice->total * $fraction),
                (float) $invoice->balance_due,
            );

            if ($amount <= 0) {
                continue;
            }

            $payments->record(
                $invoice->busCompany,
                $invoice->due_date->copy()->subDays(random_int(0, 4)),
                $amount,
                Account::system('bank'),
                [['invoice_id' => $invoice->id, 'amount' => $amount]],
                'bank',
                'TRF-'.$month->format('Ym').'-'.($index + 1),
            );
        }
    }

    private function payrollMonth(Carbon $month): void
    {
        $payroll = app(PayrollService::class);

        $run = $payroll->prepare($month);

        if ($run->isDraft()) {
            $payroll->approve($run);
            $payroll->post($run->fresh());
            $run->refresh();
            $payroll->pay($run, $month->copy()->endOfMonth()->addDays(3), (float) $run->net_total, Account::system('bank'));
        }
    }

    private function expensesFor(Carbon $month): void
    {
        $service = app(ExpenseService::class);
        $project = Project::default();
        $overhead = Project::overheadPool();
        $monthEnd = $month->copy()->endOfMonth();

        $expenses = [
            ['account' => '5030', 'dept' => 'OPS', 'project' => $project, 'desc' => 'SIM cards and mobile data for trackers', 'amount' => 1_850_000, 'nature' => 'variable', 'supplier' => 'SUP-001'],
            ['account' => '5040', 'dept' => 'TEC', 'project' => $project, 'desc' => 'Cloud hosting, SMS and push gateways', 'amount' => 2_400_000, 'nature' => 'fixed', 'supplier' => 'SUP-002', 'credit' => true],
            ['account' => '5050', 'dept' => 'OPS', 'project' => $project, 'desc' => 'Field installation and technician callouts', 'amount' => 950_000, 'nature' => 'variable'],
            ['account' => '7010', 'dept' => 'ADM', 'project' => $overhead, 'desc' => 'Office rent', 'amount' => 3_000_000, 'nature' => 'fixed', 'supplier' => 'SUP-003'],
            ['account' => '7020', 'dept' => 'ADM', 'project' => $overhead, 'desc' => 'Electricity, water and generator', 'amount' => 620_000, 'nature' => 'variable'],
            ['account' => '7030', 'dept' => 'ADM', 'project' => $overhead, 'desc' => 'Internet and telephone', 'amount' => 450_000, 'nature' => 'fixed'],
            ['account' => '7070', 'dept' => 'ADM', 'project' => $overhead, 'desc' => 'Bank charges and transfer fees', 'amount' => 95_000, 'nature' => 'variable'],
            ['account' => '8010', 'dept' => 'OPS', 'project' => $overhead, 'desc' => 'Fuel for company vehicles', 'amount' => 780_000, 'nature' => 'variable'],
            ['account' => '9010', 'dept' => 'SAL', 'project' => $overhead, 'desc' => 'Digital marketing campaign', 'amount' => 1_100_000, 'nature' => 'variable'],
            ['account' => '9050', 'dept' => 'TEC', 'project' => $overhead, 'desc' => 'Software subscriptions and developer tools', 'amount' => 540_000, 'nature' => 'fixed'],
        ];

        foreach ($expenses as $index => $definition) {
            $onCredit = $definition['credit'] ?? false;

            $expense = $service->create([
                'expense_date' => $monthEnd->copy()->subDays($index),
                'supplier_id' => isset($definition['supplier'])
                    ? Supplier::where('code', $definition['supplier'])->value('id')
                    : null,
                'account_id' => Account::byCode($definition['account'])->id,
                'department_id' => Department::where('code', $definition['dept'])->value('id'),
                'project_id' => $definition['project']?->id,
                'description' => $definition['desc'].' — '.$month->format('F Y'),
                'amount' => $definition['amount'],
                'nature' => $definition['nature'],
                'treatment' => 'opex',
                'payment_status' => $onCredit ? 'unpaid' : 'paid',
                'paid_from_account_id' => $onCredit ? null : Account::system('bank')->id,
                'due_date' => $onCredit ? $monthEnd->copy()->addDays(30) : null,
            ]);

            $service->post($expense);
        }

        // One capital purchase in the first month, so depreciation has an asset.
        if ($month->equalTo($this->start)) {
            $capex = $service->create([
                'expense_date' => $this->start->copy()->addDays(3),
                'supplier_id' => Supplier::where('code', 'SUP-004')->value('id'),
                'account_id' => Account::byCode('1130')->id,
                'department_id' => Department::where('code', 'TEC')->value('id'),
                'project_id' => $project?->id,
                'description' => 'Server and networking equipment for the eTrackify platform',
                'amount' => 18_000_000,
                'nature' => 'fixed',
                'treatment' => 'capex',
                'payment_status' => 'paid',
                'paid_from_account_id' => Account::system('bank')->id,
            ]);

            $service->post($capex);

            $service->capitalise($capex->fresh(), [
                'code' => 'FA-0001',
                'name' => 'eTrackify platform servers',
                'category' => 'it',
                'useful_life_months' => 36,
                'salvage_value' => 1_800_000,
                'depreciation_start_date' => $this->start->copy(),
            ]);
        }
    }

    private function depreciationFor(Carbon $month): void
    {
        $service = app(DepreciationService::class);
        $run = $service->prepare($month);

        if ($run->isDraft() && $run->lines()->exists()) {
            $service->post($run);
        }
    }

    private function revenueShareFor(Carbon $month): void
    {
        $service = app(RevenueShareService::class);
        $period = \App\Models\Period::forDate($month->copy()->endOfMonth());

        if (! $period) {
            return;
        }

        $run = $service->prepare($period, Project::default());

        if ($run->isDraft() && (float) $run->share_amount > 0) {
            $service->post($run);

            // Settle the first two months, leave the rest outstanding.
            if ((int) $this->start->diffInMonths($month) <= 1) {
                $service->pay(
                    $run->fresh(),
                    $month->copy()->endOfMonth()->addDays(10),
                    (float) $run->share_amount,
                    Account::system('bank'),
                );
            }
        }
    }

    private function distribution(): void
    {
        $service = app(DistributionService::class);

        $from = $this->start->copy();
        $to = $this->start->copy()->addMonths(3)->endOfMonth();

        $calculation = $service->calculate($from, $to);

        if ($calculation['distributable'] <= 0) {
            $this->command?->warn('  No distributable profit in the demo window — distribution skipped.');

            return;
        }

        // Retain a quarter of the profit in the company; distribute the rest.
        $retained = round($calculation['net_profit'] * 0.25);
        $run = $service->prepare($from, $to, $retained,
            $from->format('M').' – '.$to->format('M Y').' partner distribution');
        $service->post($run);

        // Pay two partners in full so the report shows paid and outstanding.
        foreach ($run->fresh('lines')->lines->take(2) as $line) {
            $service->payout(
                $line,
                $to->copy()->addDays(20),
                (float) $line->share_amount,
                Account::system('bank'),
            );
        }
    }
}
