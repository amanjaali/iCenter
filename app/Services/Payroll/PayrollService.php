<?php

namespace App\Services\Payroll;

use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\PostingException;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §6.6 and §8.3 — payroll, reportable per employee and per
 * department.
 *
 * Posting a run:
 *   DR 6010-6040 salaries (by the employee's own salary account)
 *   DR 6050 bonuses, 6060 overtime, 6090 allowances
 *   DR 6070 employer social security
 *     CR 2020 accrued salaries and wages   (net pay)
 *     CR 2030 payroll taxes and social security payable (withheld + employer)
 *     CR 1050 employee advances            (advances recovered)
 * Paying it:
 *   DR 2020   CR 1020 bank
 */
class PayrollService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Open a draft run for a month, pre-filled from the employee register.
     * The accountant then edits overtime, bonuses and deductions per line.
     */
    public function prepare(Carbon $month): PayrollRun
    {
        $month = $month->copy()->startOfMonth();

        if ($existing = PayrollRun::whereDate('payroll_month', $month)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($month) {
            $run = PayrollRun::create([
                'reference' => DocumentSequence::next('payroll', (int) $month->year),
                'period_id' => $this->periods->resolveFor($month->copy()->endOfMonth())->id,
                'payroll_month' => $month,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            // Staff who do not work on a named project are company overhead;
            // their cost is booked to the overhead pool and spread across
            // projects at reporting time (§7), never left untagged.
            $overheadPool = Project::overheadPool();

            $employees = Employee::active()
                ->where('hire_date', '<=', $month->copy()->endOfMonth())
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $month))
                ->orderBy('name')
                ->get();

            foreach ($employees as $employee) {
                $gross = Money::round((float) $employee->base_salary + (float) $employee->allowances);
                $employeeSs = Money::round($gross * (float) $employee->employee_ss_rate / 100);
                $employerSs = Money::round($gross * (float) $employee->employer_ss_rate / 100);

                PayrollLine::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'project_id' => $employee->project_id ?? $overheadPool?->id,
                    'salary_account_id' => $employee->salary_account_id,
                    'base_salary' => (float) $employee->base_salary,
                    'allowances' => (float) $employee->allowances,
                    'gross' => $gross,
                    'employee_ss' => $employeeSs,
                    'net' => Money::round($gross - $employeeSs),
                    'employer_ss' => $employerSs,
                ]);
            }

            return $this->recalculate($run->fresh('lines'));
        });
    }

    /** Update one line and re-total the run. */
    public function updateLine(PayrollLine $line, array $data): PayrollRun
    {
        $run = $line->run;

        if (! $run->isDraft()) {
            throw new PostingException("Payroll run {$run->reference} is no longer editable.");
        }

        $base = Money::round($data['base_salary'] ?? $line->base_salary);
        $overtime = Money::round($data['overtime'] ?? $line->overtime);
        $bonus = Money::round($data['bonus'] ?? $line->bonus);
        $allowances = Money::round($data['allowances'] ?? $line->allowances);
        $gross = Money::round($base + $overtime + $bonus + $allowances);

        $employeeSs = Money::round($data['employee_ss'] ?? ($gross * (float) $line->employee->employee_ss_rate / 100));
        $incomeTax = Money::round($data['income_tax'] ?? $line->income_tax);
        $advances = Money::round($data['advances_deducted'] ?? $line->advances_deducted);
        $other = Money::round($data['other_deductions'] ?? $line->other_deductions);

        $line->update([
            'base_salary' => $base,
            'overtime' => $overtime,
            'bonus' => $bonus,
            'allowances' => $allowances,
            'gross' => $gross,
            'employee_ss' => $employeeSs,
            'income_tax' => $incomeTax,
            'advances_deducted' => $advances,
            'other_deductions' => $other,
            'net' => Money::round($gross - $employeeSs - $incomeTax - $advances - $other),
            'employer_ss' => Money::round($data['employer_ss'] ?? ($gross * (float) $line->employee->employer_ss_rate / 100)),
            'notes' => $data['notes'] ?? $line->notes,
        ]);

        return $this->recalculate($run->fresh('lines'));
    }

    public function recalculate(PayrollRun $run): PayrollRun
    {
        $lines = $run->lines;

        $run->forceFill([
            'gross_total' => Money::round($lines->sum('gross')),
            'deductions_total' => Money::round(
                $lines->sum('employee_ss') + $lines->sum('income_tax')
                + $lines->sum('advances_deducted') + $lines->sum('other_deductions')
            ),
            'employer_ss_total' => Money::round($lines->sum('employer_ss')),
            'net_total' => Money::round($lines->sum('net')),
        ])->save();

        return $run->refresh('lines');
    }

    public function approve(PayrollRun $run): PayrollRun
    {
        if (! $run->isDraft()) {
            throw new PostingException("Payroll run {$run->reference} is already {$run->status}.");
        }

        $run->update(['status' => 'approved', 'approved_by' => Auth::id()]);

        $this->audit->record('payroll_approved', $run, description: "Approved payroll {$run->reference}");

        return $run->refresh();
    }

    /** Post the run to the ledger. */
    public function post(PayrollRun $run): PayrollRun
    {
        if ($run->isPosted()) {
            throw new PostingException("Payroll run {$run->reference} has already been posted.");
        }

        $run->loadMissing('lines.employee', 'lines.salaryAccount');

        if ($run->lines->isEmpty()) {
            throw new PostingException("Payroll run {$run->reference} has no employees.");
        }

        return DB::transaction(function () use ($run) {
            $this->recalculate($run);
            $run->refresh();

            $postingDate = $run->payroll_month->copy()->endOfMonth();
            $lines = [];

            // Base pay charged to each employee's own class 6000 account, so
            // the payroll report splits cleanly by department and function.
            foreach ($run->lines->groupBy(['salary_account_id', 'department_id']) as $accountId => $byDepartment) {
                foreach ($byDepartment as $departmentId => $group) {
                    $base = Money::round($group->sum('base_salary'));

                    if ($base > 0) {
                        $lines[] = [
                            'account_id' => (int) $accountId,
                            'description' => 'Salaries — '.$run->payroll_month->format('F Y'),
                            'debit' => $base,
                            'department_id' => (int) $departmentId,
                            'project_id' => $group->first()->project_id,
                            'nature' => 'fixed',
                            'treatment' => 'opex',
                        ];
                    }
                }
            }

            // The variable elements go to their own accounts (§6.6).
            foreach ([
                ['column' => 'bonus', 'code' => '6050', 'label' => 'Bonuses and incentives', 'nature' => 'variable'],
                ['column' => 'overtime', 'code' => '6060', 'label' => 'Overtime', 'nature' => 'variable'],
                ['column' => 'allowances', 'code' => '6090', 'label' => 'Staff allowances', 'nature' => 'variable'],
                ['column' => 'employer_ss', 'code' => '6070', 'label' => 'Social security — employer contribution', 'nature' => 'fixed'],
            ] as $bucket) {
                foreach ($run->lines->groupBy('department_id') as $departmentId => $group) {
                    $amount = Money::round($group->sum($bucket['column']));

                    if ($amount <= 0) {
                        continue;
                    }

                    $lines[] = [
                        'account' => $bucket['code'],
                        'description' => $bucket['label'].' — '.$run->payroll_month->format('F Y'),
                        'debit' => $amount,
                        'department_id' => (int) $departmentId,
                        'project_id' => $group->first()->project_id,
                        'nature' => $bucket['nature'],
                        'treatment' => 'opex',
                    ];
                }
            }

            $net = Money::round($run->lines->sum('net'));
            $withheld = Money::round($run->lines->sum('employee_ss') + $run->lines->sum('income_tax'));
            $employerSs = Money::round($run->lines->sum('employer_ss'));
            $advances = Money::round($run->lines->sum('advances_deducted'));
            $other = Money::round($run->lines->sum('other_deductions'));

            if ($net > 0) {
                $lines[] = [
                    'account' => Account::system('accrued_salaries'),
                    'description' => 'Net pay due — '.$run->payroll_month->format('F Y'),
                    'credit' => $net,
                ];
            }

            if ($withheld + $employerSs > 0) {
                $lines[] = [
                    'account' => Account::system('payroll_taxes'),
                    'description' => 'Social security and tax payable — '.$run->payroll_month->format('F Y'),
                    'credit' => Money::round($withheld + $employerSs),
                ];
            }

            if ($advances > 0) {
                // Recovering an advance clears the asset raised when it was paid.
                $lines[] = [
                    'account' => Account::system('employee_advances'),
                    'description' => 'Advances recovered — '.$run->payroll_month->format('F Y'),
                    'credit' => $advances,
                ];
            }

            if ($other > 0) {
                $lines[] = [
                    'account' => Account::system('accrued_salaries'),
                    'description' => 'Other deductions withheld — '.$run->payroll_month->format('F Y'),
                    'credit' => $other,
                ];
            }

            $journal = $this->journals->post(
                $postingDate,
                "Payroll {$run->reference} — {$run->payroll_month->format('F Y')}",
                $lines,
                JournalSource::Payroll,
                $run,
            );

            $run->forceFill([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ])->save();

            $this->audit->record(
                'payroll_posted',
                $run,
                newValues: [
                    'employees' => $run->lines->count(),
                    'gross' => (float) $run->gross_total,
                    'net' => (float) $run->net_total,
                    'journal' => $journal->reference,
                ],
                description: "Posted payroll {$run->reference}",
            );

            return $run->refresh();
        });
    }

    /** Pay the accrued net salaries. */
    public function pay(
        PayrollRun $run,
        Carbon $date,
        float $amount,
        Account $sourceAccount,
        string $method = 'bank',
    ): PayrollPayment {
        $amount = Money::round($amount);

        if (! $run->isPosted()) {
            throw new PostingException("Post payroll run {$run->reference} before paying it.");
        }

        if ($amount <= 0 || $amount > $run->outstandingAmount() + 0.005) {
            throw new PostingException(
                'The payment must be between zero and the outstanding '.Money::format($run->outstandingAmount()).'.'
            );
        }

        return DB::transaction(function () use ($run, $date, $amount, $sourceAccount, $method) {
            $payment = PayrollPayment::create([
                'reference' => DocumentSequence::next('payment', (int) $date->year),
                'payroll_run_id' => $run->id,
                'payment_date' => $date,
                'amount' => $amount,
                'method' => $method,
                'source_account_id' => $sourceAccount->id,
                'created_by' => Auth::id(),
            ]);

            $journal = $this->journals->post(
                $date,
                "Salary payment — {$run->payroll_month->format('F Y')}",
                [
                    [
                        'account' => Account::system('accrued_salaries'),
                        'description' => 'Salaries paid — '.$run->payroll_month->format('F Y'),
                        'debit' => $amount,
                    ],
                    [
                        'account' => $sourceAccount,
                        'description' => 'Payroll — '.$run->payroll_month->format('F Y'),
                        'credit' => $amount,
                    ],
                ],
                JournalSource::PayrollPayment,
                $payment,
            );

            $payment->update(['journal_id' => $journal->id]);

            $paid = Money::round((float) $run->payments()->sum('amount'));

            $run->forceFill([
                'paid_amount' => $paid,
                'payment_date' => $date,
                'status' => $paid >= (float) $run->net_total - 0.005 ? 'paid' : 'posted',
            ])->save();

            return $payment->refresh();
        });
    }
}
