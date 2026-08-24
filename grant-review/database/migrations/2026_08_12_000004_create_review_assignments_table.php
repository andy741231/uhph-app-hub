<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete(); // must have role='reviewer'
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique(['submission_id', 'reviewer_id'], 'uniq_submission_reviewer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_assignments');
    }
};
