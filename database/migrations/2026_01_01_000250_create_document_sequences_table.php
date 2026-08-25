<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gapless document numbering. Handed out under a row lock inside the
        // caller's transaction so two accountants posting at the same moment
        // cannot be issued the same invoice number.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['document_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
