<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §4.3 — one invoice per bus company per month.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('bus_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->date('billing_month');       // always the 1st of the billed month
            $table->date('issue_date');
            $table->date('due_date');

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            // Kept as a stored column so the ageing report does not have to
            // join payments for every row.
            $table->decimal('balance_due', 18, 2)->default(0)->index();

            $table->unsignedInteger('student_count')->default(0);
            $table->decimal('billable_units', 14, 4)->default(0); // student-months after proration
            $table->string('proration_method', 20);              // the rule applied, frozen on the invoice

            // draft|issued|partially_paid|paid|void
            $table->string('status', 20)->default('draft')->index();

            // §4.4 — an invoice raised for a future month credits deferred
            // revenue (2060) and is released to 4010 when the month arrives.
            $table->boolean('is_deferred')->default(false);
            $table->boolean('revenue_recognised')->default(false)->index();
            $table->date('recognised_on')->nullable();
            $table->foreignId('recognition_journal_id')->nullable()->constrained('journals')->nullOnDelete();

            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One invoice per company per month — re-running generation for a
            // month that is already billed is rejected, not duplicated.
            $table->unique(['bus_company_id', 'billing_month'], 'invoices_company_month_unique');
            $table->index(['billing_month', 'status']);
        });

        // One line per bus, so a bus company statement can show students per bus.
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('student_count')->default(0);
            $table->decimal('billable_units', 14, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->timestamps();
        });

        // Per-student detail behind each line. The scope of work demands that
        // no item of income can be lost or left untracked, so the billed days
        // for every individual student are retained and reproducible.
        Schema::create('invoice_line_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('billable_days')->default(0);
            $table->unsignedSmallInteger('days_in_month')->default(0);
            $table->decimal('billable_units', 14, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->boolean('is_partial_month')->default(false);
            $table->string('note', 200)->nullable();   // "joined 12 Aug", "left 20 Aug"

            $table->index('student_id');
        });

        // Scope of work §4.3 — collections.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('bus_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('allocated_amount', 18, 2)->default(0);
            // Unallocated cash from a company paying several months in advance.
            $table->decimal('unallocated_amount', 18, 2)->default(0);
            $table->string('method', 30)->default('bank');  // cash|bank|cheque|transfer
            $table->foreignId('deposit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft|posted|void
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_line_students');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
