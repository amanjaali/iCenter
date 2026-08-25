<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §7 — the cost centre tag every expense must carry.
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->unsignedInteger('headcount_weight')->default(0); // used by headcount overhead allocation
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Scope of work §7 — the project tag. eTrackify plus any other ALLVA
        // project, so that project profitability separates from overhead.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            // Overhead is booked against the company, not a project, and is
            // spread across projects at reporting time.
            $table->boolean('is_overhead_pool')->default(false);
            $table->boolean('receives_overhead')->default(true);
            $table->date('started_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('departments');
    }
};
