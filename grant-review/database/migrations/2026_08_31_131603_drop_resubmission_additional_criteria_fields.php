<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the "Resubmission / Renewal / Revisions" additional review
     * criteria columns from the reviews and review_revisions tables.
     * The field is no longer collected from reviewers.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'additional_resubmission_comments')) {
                $table->dropColumn('additional_resubmission_comments');
            }
            if (Schema::hasColumn('reviews', 'additional_resubmission')) {
                $table->dropColumn('additional_resubmission');
            }
        });

        Schema::table('review_revisions', function (Blueprint $table) {
            if (Schema::hasColumn('review_revisions', 'additional_resubmission_comments')) {
                $table->dropColumn('additional_resubmission_comments');
            }
            if (Schema::hasColumn('review_revisions', 'additional_resubmission')) {
                $table->dropColumn('additional_resubmission');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'additional_resubmission')) {
                $table->string('additional_resubmission', 3)->nullable()->after('additional_biohazards_comments');
                $table->text('additional_resubmission_comments')->nullable()->after('additional_resubmission');
            }
        });

        Schema::table('review_revisions', function (Blueprint $table) {
            if (! Schema::hasColumn('review_revisions', 'additional_resubmission')) {
                $table->string('additional_resubmission', 3)->nullable()->after('additional_biohazards_comments');
                $table->text('additional_resubmission_comments')->nullable()->after('additional_resubmission');
            }
        });
    }
};
