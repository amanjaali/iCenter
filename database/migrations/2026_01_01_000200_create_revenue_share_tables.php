<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §5.1 — Cyber Gate's 50%.
        // The basis is stored on the run itself, not read from settings at
        // report time, so a later change to the setting can never silently
        // restate a period that has already been posted (§10.1).
        Schema::create('revenue_share_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner_name')->default('Cyber Gate');

            $table->string('basis', 10);                 // gross|net
            $table->decimal('share_percent', 7, 4);
            $table->decimal('gross_revenue', 18, 2)->default(0);
            $table->decimal('operating_expenses', 18, 2)->default(0); // only used when basis = net
            $table->decimal('revenue_base', 18, 2)->default(0);       // the figure the % is applied to
            $table->decimal('share_amount', 18, 2)->default(0);

            $table->string('status', 20)->default('draft')->index(); // draft|posted|settled
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'project_id'], 'rev_share_period_project_unique');
        });

        Schema::create('revenue_share_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_share_run_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method', 30)->default('bank');
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_share_payments');
        Schema::dropIfExists('revenue_share_runs');
    }
};
