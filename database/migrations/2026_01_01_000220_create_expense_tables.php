<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Scope of work §7. Every expense carries three tags — account,
        // cost centre and project — plus the fixed/variable and capex/opex
        // classifications the section requires the system to report on.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('expense_date')->index();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // For opex this is the P&L account charged; for capex it is the
            // fixed asset account the cost is capitalised into.
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();

            $table->string('description', 500);
            $table->decimal('amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2);

            $table->string('nature', 12)->default('variable');   // fixed|variable
            $table->string('treatment', 12)->default('opex');    // capex|opex

            // unpaid -> credits 2010 Accounts payable
            // paid   -> credits the cash or bank account directly
            $table->string('payment_status', 12)->default('paid'); // paid|unpaid
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->decimal('amount_paid', 18, 2)->default(0);

            $table->string('status', 20)->default('draft')->index(); // draft|posted|void
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attachment_path')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'status']);
            $table->index(['department_id', 'project_id']);
        });

        // Settling an unpaid expense (a supplier bill) — debits 2010.
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 18, 2);
            $table->string('method', 30)->default('bank');
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('reference_note', 120)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('suppliers');
    }
};
