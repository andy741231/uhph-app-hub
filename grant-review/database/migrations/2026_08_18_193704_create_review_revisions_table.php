<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks each submission of a review — a reviewer can continually
     * update and re-submit their score and comments. Each submission
     * creates a new revision record, so the full timeline of when/what
     * is preserved. The Review model always reflects the latest state.
     */
    public function up(): void
    {
        Schema::create('review_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('comments')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->index(['review_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_revisions');
    }
};
