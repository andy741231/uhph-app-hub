<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('document_flags')) {
            return;
        }

        Schema::create('document_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('flag_word_id')->constrained('document_flag_words')->onDelete('cascade');
            $table->unsignedInteger('occurrences')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'flag_word_id']);
            $table->index('flag_word_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_flags');
    }
};
