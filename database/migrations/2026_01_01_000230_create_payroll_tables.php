<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §6.6 — ALLVA employs staff and pays salaries; the
        // payroll report is required per employee and per department (§8.3).
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            // Which class 6000 salary account this employee's pay is charged to
            // (6010 management, 6020 technical, 6030 sales, 6040 field).
            $table->foreignId('salary_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('position')->nullable();
            $table->string('employment_type', 20)->default('full_time');
            $table->date('hire_date');
            $table->date('end_date')->nullable();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('allowances', 18, 2)->default(0);
            $table->decimal('employee_ss_rate', 6, 3)->default(5.000);   // % withheld
            $table->decimal('employer_ss_rate', 6, 3)->default(12.000);  // % employer cost (6070)
            $table->string('bank_name')->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->date('payroll_month');   // 1st of the month
            $table->date('payment_date')->nullable();

            $table->decimal('gross_total', 18, 2)->default(0);
            $table->decimal('deductions_total', 18, 2)->default(0);
            $table->decimal('employer_ss_total', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);

            $table->string('status', 20)->default('draft')->index(); // draft|approved|posted|paid
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique('payroll_month');
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salary_account_id')->constrained('accounts')->restrictOnDelete();

            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('overtime', 18, 2)->default(0);      // 6060
            $table->decimal('bonus', 18, 2)->default(0);         // 6050
            $table->decimal('allowances', 18, 2)->default(0);    // 6090
            $table->decimal('gross', 18, 2)->default(0);

            $table->decimal('employee_ss', 18, 2)->default(0);   // withheld -> 2030
            $table->decimal('income_tax', 18, 2)->default(0);    // withheld -> 2030
            $table->decimal('advances_deducted', 18, 2)->default(0); // clears 1050
            $table->decimal('other_deductions', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);           // -> 2020

            $table->decimal('employer_ss', 18, 2)->default(0);   // employer cost -> 6070
            $table->string('notes', 300)->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_run_employee_unique');
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 30)->default('bank');
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employees');
    }
};
