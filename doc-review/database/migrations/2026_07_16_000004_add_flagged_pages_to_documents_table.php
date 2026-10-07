<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documents', 'flagged_pages')) {
            Schema::table('documents', function (Blueprint $table) {
                // JSON: [{page: 1, words: [{word: "foo", occurrences: 2}]}, ...]
                $table->json('flagged_pages')->nullable()->after('flag_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documents', 'flagged_pages')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('flagged_pages');
            });
        }
    }
};
