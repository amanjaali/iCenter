<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §2. Five partners at 20% each — but the percentage is a
        // column, not a constant, so a change in shareholding never requires
        // the distribution logic to be rebuilt.
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->decimal('ownership_percent', 7, 4)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('capital_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // §9.3 — ownership changes must be logged. Kept as a first-class table
        // rather than only an audit row because distributions are recalculated
        // against the percentage in force on the distribution date.
        Schema::create('ownership_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_percent', 7, 4);
            $table->decimal('new_percent', 7, 4);
            $table->date('effective_date');
            $table->text('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_change_logs');
        Schema::dropIfExists('partners');
    }
};
