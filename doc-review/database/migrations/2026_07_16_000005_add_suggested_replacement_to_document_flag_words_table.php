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
        if (! Schema::hasColumn('document_flag_words', 'suggested_replacement')) {
            Schema::table('document_flag_words', function (Blueprint $table) {
                $table->string('suggested_replacement')->nullable()->after('word');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('document_flag_words', 'suggested_replacement')) {
            Schema::table('document_flag_words', function (Blueprint $table) {
                $table->dropColumn('suggested_replacement');
            });
        }
    }
};
