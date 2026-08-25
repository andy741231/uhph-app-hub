<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_of_interest_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->timestamp('declared_at')->useCurrent();
            $table->timestamps();
            $table->unique(['reviewer_id', 'round_id'], 'uniq_reviewer_round_coi');
        });

        Schema::create('conflict_of_interest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declaration_id')->constrained('conflict_of_interest_declarations')->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['declaration_id', 'submission_id'], 'uniq_declaration_submission_coi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_of_interest_entries');
        Schema::dropIfExists('conflict_of_interest_declarations');
    }
};
