<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dateTime('reviews_released_to_reviewers_at')->nullable()->after('submitted_at');
            $table->foreignId('reviews_released_to_reviewers_by')->nullable()->after('reviews_released_to_reviewers_at')->constrained('users')->nullOnDelete();
            $table->dateTime('reviews_released_to_submitter_at')->nullable()->after('reviews_released_to_reviewers_by');
            $table->foreignId('reviews_released_to_submitter_by')->nullable()->after('reviews_released_to_submitter_at')->constrained('users')->nullOnDelete();
        });

        // Existing releases applied to both audiences at once — preserve that.
        DB::table('submissions')->whereNotNull('reviews_released_at')->update([
            'reviews_released_to_reviewers_at' => DB::raw('reviews_released_at'),
            'reviews_released_to_reviewers_by' => DB::raw('reviews_released_by'),
            'reviews_released_to_submitter_at' => DB::raw('reviews_released_at'),
            'reviews_released_to_submitter_by' => DB::raw('reviews_released_by'),
        ]);

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviews_released_by');
            $table->dropColumn('reviews_released_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dateTime('reviews_released_at')->nullable()->after('submitted_at');
            $table->foreignId('reviews_released_by')->nullable()->after('reviews_released_at')->constrained('users')->nullOnDelete();
        });

        DB::table('submissions')->update([
            'reviews_released_at' => DB::raw('COALESCE(reviews_released_to_reviewers_at, reviews_released_to_submitter_at)'),
            'reviews_released_by' => DB::raw('COALESCE(reviews_released_to_reviewers_by, reviews_released_to_submitter_by)'),
        ]);

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviews_released_to_reviewers_by');
            $table->dropConstrainedForeignId('reviews_released_to_submitter_by');
            $table->dropColumn([
                'reviews_released_to_reviewers_at',
                'reviews_released_to_submitter_at',
            ]);
        });
    }
};
