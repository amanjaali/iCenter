<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §3.1 — the operational registry.
        // bus company -> bus -> students. This chain is the source of all
        // billing; invoices are generated from it, never typed by hand.
        Schema::create('bus_companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(15);
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('status', 20)->default('active')->index(); // active|suspended|closed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_company_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);                 // internal bus number
            $table->string('plate_number', 40)->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 32)->nullable();
            $table->string('route_name')->nullable();
            $table->string('device_serial', 60)->nullable();   // eTrackify tracker
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bus_company_id', 'code']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('school_name')->nullable();
            $table->string('grade', 40)->nullable();
            $table->string('ble_tag', 60)->nullable();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('status', 20)->default('active')->index(); // active|inactive|left
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bus_company_id', 'status']);
            $table->index(['bus_id', 'status']);
        });

        // The billable history. A student who moves between buses, pauses, or
        // leaves and returns produces several rows; billing counts days from
        // these, never from students.status. Without this table a mid-month
        // change would be unbillable and a past invoice unreproducible.
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bus_company_id')->constrained()->restrictOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();  // null = still enrolled
            $table->string('start_reason', 60)->nullable(); // enrolled|transferred|resumed
            $table->string('end_reason', 60)->nullable();   // left|transferred|suspended
            $table->boolean('is_billable')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bus_company_id', 'start_date', 'end_date']);
            $table->index(['student_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('buses');
        Schema::dropIfExists('bus_companies');
    }
};
