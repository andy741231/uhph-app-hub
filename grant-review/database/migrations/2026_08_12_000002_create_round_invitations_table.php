<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // must have role='submitter'
            $table->timestamp('invited_at')->useCurrent();
            $table->unique(['round_id', 'user_id'], 'uniq_round_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_invitations');
    }
};
