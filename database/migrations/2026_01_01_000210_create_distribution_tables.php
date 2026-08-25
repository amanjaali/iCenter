<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §5.2 — net profit distributed among the partners.
        // A run always covers an explicit date range so that a partner can see
        // exactly which months a payment covers.
        Schema::create('distribution_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('title')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('period_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('gross_revenue', 18, 2)->default(0);
            $table->decimal('direct_costs', 18, 2)->default(0);
            $table->decimal('operating_expenses', 18, 2)->default(0);
            $table->decimal('net_profit', 18, 2)->default(0);
            // Partners may agree to retain part of the profit in the company.
            $table->decimal('retained_amount', 18, 2)->default(0);
            $table->decimal('distributable_amount', 18, 2)->default(0);
            $table->decimal('distributed_amount', 18, 2)->default(0);

            $table->string('status', 20)->default('draft')->index(); // draft|posted|settled
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });

        Schema::create('distribution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            // Frozen at the moment of posting — a later ownership change does
            // not restate a distribution that has already been declared.
            $table->decimal('ownership_percent', 7, 4);
            $table->decimal('share_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0)->index();
            $table->timestamps();

            $table->unique(['distribution_run_id', 'partner_id'], 'dist_run_partner_unique');
        });

        Schema::create('partner_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('distribution_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->date('payout_date')->index();
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
        Schema::dropIfExists('partner_payouts');
        Schema::dropIfExists('distribution_lines');
        Schema::dropIfExists('distribution_runs');
    }
};
