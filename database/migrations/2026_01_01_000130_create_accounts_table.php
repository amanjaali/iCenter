<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scope of work §6. The class numbering (1000..9000) is fixed by the
        // specification; sub-accounts may be added inside a class.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('type', 20)->index();      // asset|liability|equity|revenue|expense
            $table->string('subtype', 40)->nullable()->index();
            $table->string('normal_balance', 10);     // debit|credit
            $table->unsignedSmallInteger('class');    // 1000..9000, derived from code
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('description')->nullable();

            // A header account groups its children and may not be posted to.
            $table->boolean('is_postable')->default(true);
            // System accounts are referenced by the posting engine by code and
            // may not be renumbered or deleted.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();

            // §7 — fixed vs variable is a property of the expense line, but a
            // default per account removes most of the data entry.
            $table->string('default_nature', 12)->nullable();  // fixed|variable
            $table->boolean('requires_department')->default(false);
            $table->boolean('requires_project')->default(false);

            // Cash and bank accounts appear in the cash flow statement and in
            // the "pay from" pickers.
            $table->boolean('is_cash_equivalent')->default(false)->index();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['class', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
