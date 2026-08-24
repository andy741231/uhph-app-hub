<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitter_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('abstract')->nullable();
            $table->decimal('amount_requested', 12, 2)->nullable();
            $table->string('pdf_path', 500); // file path or storage key
            // under_review set on first review_assignment creation
            $table->enum('status', ['draft', 'submitted', 'under_review', 'decided'])->default('draft');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['round_id', 'submitter_id'], 'uniq_round_submitter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
