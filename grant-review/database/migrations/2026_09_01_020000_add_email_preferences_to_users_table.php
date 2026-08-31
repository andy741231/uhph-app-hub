<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user email notification preferences stored as a JSON object.
     * Each key maps to a boolean (true = receive that notification).
     * Defaults to all notifications enabled.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('email_preferences')->nullable()->after('key_personnel');
        });

        // Enable all notifications by default for existing users
        DB::table('users')->update([
            'email_preferences' => json_encode([
                'notify_profile_completed' => true,
                'notify_review_submitted' => true,
                'notify_proposal_submitted' => true,
                'notify_all_reviews_complete' => true,
                'notify_decision_recorded' => true,
                'notify_reviewer_assigned' => true,
                'notify_submission_confirmation' => true,
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_preferences');
        });
    }
};
