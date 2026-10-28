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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Nullable FK: the actor link is best-effort — the name/email
            // snapshots below are the durable record after a user is renamed
            // or deleted.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_email');
            // login | document_uploaded | document_deleted
            $table->string('action')->index();
            // Deliberately NOT a foreign key: audit rows keep the historical
            // id and filename after the document is deleted.
            $table->unsignedBigInteger('document_id')->nullable();
            $table->text('document_name')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
