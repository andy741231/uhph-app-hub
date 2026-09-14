<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Set when an admin un-releases reviews to the submitter: the
            // release button is king, so an explicit un-release reopens
            // proposal editing even after the round deadline has passed.
            // Cleared again when reviews are re-released to the submitter.
            $table->dateTime('submission_edit_unlocked_at')->nullable()->after('reviews_released_to_submitter_by');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('submission_edit_unlocked_at');
        });
    }
};
