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
        Schema::table('document_flag_words', function (Blueprint $table) {
            // DB-level default preserves every existing row and any raw/legacy
            // insert as word_root. New Eloquent rows default to whole_word via
            // the model's $attributes (see DocumentFlagWord).
            $table->string('match_type')->default('word_root')->after('suggested_replacement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_flag_words', function (Blueprint $table) {
            $table->dropColumn('match_type');
        });
    }
};
