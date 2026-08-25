<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §4.3 / §10.3 — the academic year, and whether billing
        // pauses over the summer and school holidays. The decision is stored
        // per year, so 2026-2027 and 2027-2028 may differ.
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();       // "2026-2027"
            $table->date('start_date');
            $table->date('end_date');
            // continue = bill all twelve months
            // pause    = bill only the months listed in billable_months
            $table->string('billing_mode', 12)->default('continue');
            $table->json('billable_months')->nullable(); // [9,10,11,12,1,2,3,4,5,6]
            $table->boolean('is_active')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Scope of work §4.2 — the rate table. Effective-dated so that a
        // historical month is always billed at the rate that applied then,
        // even after a future price increase.
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            // null = the general rate for all bus companies.
            // set  = a special agreement overriding the general rate.
            $table->foreignId('bus_company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('period', 30)->default('per_student_month');
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();  // null = open ended
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bus_company_id', 'effective_from', 'effective_to']);
        });

        // §4.2 — "Any rate change must be logged with the user who made it, the
        // date, and the reason." A reason is mandatory at the application layer.
        Schema::create('rate_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 20);   // created|updated|deactivated
            $table->decimal('old_amount', 18, 2)->nullable();
            $table->decimal('new_amount', 18, 2)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_change_logs');
        Schema::dropIfExists('rate_cards');
        Schema::dropIfExists('academic_years');
    }
};
