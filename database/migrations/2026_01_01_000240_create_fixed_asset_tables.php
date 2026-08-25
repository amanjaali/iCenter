<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §7 — capital expenditure is capitalised and
        // depreciated rather than charged in the period incurred.
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category', 30);  // vehicle|furniture|it|intangible
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            // 8050 for vehicles, 9060 for other assets, 9070 for intangibles.
            $table->foreignId('depreciation_expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete(); // the capex that created it

            $table->date('acquisition_date');
            $table->date('depreciation_start_date');
            $table->decimal('cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('method', 20)->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);

            $table->string('status', 20)->default('active')->index(); // active|fully_depreciated|disposed
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 18, 2)->nullable();
            $table->foreignId('disposal_journal_id')->nullable()->constrained('journals')->nullOnDelete();

            $table->string('serial_number', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->date('run_month');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft|posted
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique('run_month');
        });

        Schema::create('depreciation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depreciation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('accumulated_after', 18, 2);
            $table->decimal('net_book_value_after', 18, 2);
            $table->timestamps();

            $table->unique(['depreciation_run_id', 'fixed_asset_id'], 'dep_run_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_lines');
        Schema::dropIfExists('depreciation_runs');
        Schema::dropIfExists('fixed_assets');
    }
};
