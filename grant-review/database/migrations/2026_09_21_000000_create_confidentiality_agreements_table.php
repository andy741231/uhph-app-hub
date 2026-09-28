<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records each reviewer's acceptance of the Confidentiality
     * Statement & Code of Conduct, captured alongside the per-cycle
     * COI declaration. One row per reviewer + cycle + document
     * version — resubmissions of the same version keep the original
     * timestamp, while a new version creates a fresh record, so the
     * admin profile shows exactly which wording was agreed to and
     * when.
     *
     * Guarded so the migration is safe to re-run after a partially
     * applied DDL attempt (MySQL does not roll back DDL).
     */
    public function up(): void
    {
        if (! Schema::hasTable('confidentiality_agreements')) {
            Schema::create('confidentiality_agreements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('round_id')->constrained()->cascadeOnDelete();
                $table->string('version', 20);
                $table->text('content');
                $table->timestamps();
                $table->unique(['user_id', 'round_id', 'version'], 'uniq_confidentiality_user_round_version');
                $table->index(['user_id', 'created_at'], 'idx_confidentiality_user_agreed');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('confidentiality_agreements');
    }
};
