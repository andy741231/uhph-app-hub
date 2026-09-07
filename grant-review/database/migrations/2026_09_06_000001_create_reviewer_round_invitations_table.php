<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviewer screening invitations: an admin invites a reviewer to
     * screen a round for conflicts of interest BEFORE any proposal is
     * assigned. This is distinct from submitter round invitations and
     * from the reviewer role itself.
     */
    public function up(): void
    {
        Schema::create('reviewer_round_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['round_id', 'reviewer_id'], 'uniq_round_reviewer_invitation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_round_invitations');
    }
};
