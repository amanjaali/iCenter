<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The general ledger. Every financial event in the system — an invoice,
        // a payment, a payroll run, a depreciation charge, a partner payout —
        // lands here as a balanced journal. Nothing is ever deleted: §9.3
        // requires corrections to be made by reversal.
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->date('journal_date')->index();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->string('memo', 500)->nullable();

            // Where the journal came from. Manual journals are the only ones a
            // user types by hand; the rest are produced by the posting engine
            // and point back at the document that created them.
            $table->string('source_type', 40)->default('manual')->index();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('status', 12)->default('draft')->index(); // draft|posted|reversed
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);

            // Reversal pair. reversal_of_id points at the journal this one
            // reverses; reversed_by_id is the back-reference.
            $table->foreignId('reversal_of_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'journal_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);

            // §7 — the three tags every entry carries. Department and project
            // are nullable on balance sheet lines, required on expense lines
            // (enforced in App\Services\Accounting\JournalService).
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // Subsidiary ledger pointers, so a control account can be broken
            // down without scanning descriptions.
            $table->foreignId('partner_id')->nullable();
            $table->foreignId('bus_company_id')->nullable();
            $table->foreignId('supplier_id')->nullable();
            $table->foreignId('employee_id')->nullable();

            // §7 — classification carried onto the ledger line so the
            // fixed/variable and capex/opex reports do not need to re-derive it.
            $table->string('nature', 12)->nullable();     // fixed|variable
            $table->string('treatment', 12)->nullable();  // capex|opex

            $table->timestamps();

            $table->index(['account_id', 'journal_id']);
            $table->index(['department_id', 'project_id']);
            $table->index('bus_company_id');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
    }
};
