<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redesign the review form to follow the NIH simplified peer-review
     * framework: Overall Impact (1–9), three scored/evaluated Factors,
     * and four Additional Review Criteria (Yes/No/N/A + comment).
     *
     * The existing `score` column is repurposed from a 0–100 decimal to
     * the 1–9 Overall Impact score; `comments` becomes the Overall Impact
     * comment.  New columns store the factor scores, Factor 3 sufficiency,
     * and additional-criteria statuses with their comments.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->tinyInteger('factor1_score')->nullable()->after('comments');
            $table->text('factor1_comments')->nullable()->after('factor1_score');
            $table->tinyInteger('factor2_score')->nullable()->after('factor1_comments');
            $table->text('factor2_comments')->nullable()->after('factor2_score');
            $table->boolean('factor3_sufficient')->nullable()->after('factor2_comments');
            $table->text('factor3_comments')->nullable()->after('factor3_sufficient');
            $table->string('additional_human_subjects', 3)->nullable()->after('factor3_comments');
            $table->text('additional_human_subjects_comments')->nullable()->after('additional_human_subjects');
            $table->string('additional_vertebrate_animals', 3)->nullable()->after('additional_human_subjects_comments');
            $table->text('additional_vertebrate_animals_comments')->nullable()->after('additional_vertebrate_animals');
            $table->string('additional_biohazards', 3)->nullable()->after('additional_vertebrate_animals_comments');
            $table->text('additional_biohazards_comments')->nullable()->after('additional_biohazards');
        });

        Schema::table('review_revisions', function (Blueprint $table) {
            $table->tinyInteger('factor1_score')->nullable()->after('comments');
            $table->text('factor1_comments')->nullable()->after('factor1_score');
            $table->tinyInteger('factor2_score')->nullable()->after('factor1_comments');
            $table->text('factor2_comments')->nullable()->after('factor2_score');
            $table->boolean('factor3_sufficient')->nullable()->after('factor2_comments');
            $table->text('factor3_comments')->nullable()->after('factor3_sufficient');
            $table->string('additional_human_subjects', 3)->nullable()->after('factor3_comments');
            $table->text('additional_human_subjects_comments')->nullable()->after('additional_human_subjects');
            $table->string('additional_vertebrate_animals', 3)->nullable()->after('additional_human_subjects_comments');
            $table->text('additional_vertebrate_animals_comments')->nullable()->after('additional_vertebrate_animals');
            $table->string('additional_biohazards', 3)->nullable()->after('additional_vertebrate_animals_comments');
            $table->text('additional_biohazards_comments')->nullable()->after('additional_biohazards');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'factor1_score',
                'factor1_comments',
                'factor2_score',
                'factor2_comments',
                'factor3_sufficient',
                'factor3_comments',
                'additional_human_subjects',
                'additional_human_subjects_comments',
                'additional_vertebrate_animals',
                'additional_vertebrate_animals_comments',
                'additional_biohazards',
                'additional_biohazards_comments',
            ]);
        });

        Schema::table('review_revisions', function (Blueprint $table) {
            $table->dropColumn([
                'factor1_score',
                'factor1_comments',
                'factor2_score',
                'factor2_comments',
                'factor3_sufficient',
                'factor3_comments',
                'additional_human_subjects',
                'additional_human_subjects_comments',
                'additional_vertebrate_animals',
                'additional_vertebrate_animals_comments',
                'additional_biohazards',
                'additional_biohazards_comments',
            ]);
        });
    }
};
