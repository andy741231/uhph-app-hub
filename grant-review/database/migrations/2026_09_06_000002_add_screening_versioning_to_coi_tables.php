<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versioned COI declarations with explicit per-proposal responses.
     *
     * - Declarations become append-only: a resubmission supersedes the
     *   previous declaration instead of mutating it, preserving history.
     *   The old unique (reviewer_id, round_id) constraint is replaced by
     *   a plain index to allow multiple versions.
     * - Each declaration records one response per screened proposal
     *   (clear or potential_conflict), so "no conflict" is provable
     *   rather than inferred from a missing row.
     * - admin_notified_at tracks whether the admin result email was
     *   accepted for delivery.
     *
     * Each step is guarded so the migration is safe to re-run after a
     * partially applied DDL attempt (MySQL does not roll back DDL).
     */
    public function up(): void
    {
        Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
            if (! Schema::hasColumn('conflict_of_interest_declarations', 'reviewer_round_invitation_id')) {
                $table->foreignId('reviewer_round_invitation_id')->nullable();
            }
            if (! Schema::hasColumn('conflict_of_interest_declarations', 'admin_notified_at')) {
                $table->timestamp('admin_notified_at')->nullable();
            }
            if (! Schema::hasColumn('conflict_of_interest_declarations', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable();
            }
        });

        if (! $this->foreignKeyExists('coi_decl_invitation_fk')) {
            Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
                $table->foreign('reviewer_round_invitation_id', 'coi_decl_invitation_fk')
                    ->references('id')->on('reviewer_round_invitations')
                    ->nullOnDelete();
            });
        }

        if (! $this->indexExists('idx_coi_reviewer_round_current')) {
            Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
                $table->index(['reviewer_id', 'round_id', 'superseded_at'], 'idx_coi_reviewer_round_current');
            });
        }

        if ($this->indexExists('uniq_reviewer_round_coi')) {
            Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
                $table->dropUnique('uniq_reviewer_round_coi');
            });
        }

        if (! Schema::hasTable('conflict_of_interest_responses')) {
            Schema::create('conflict_of_interest_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('declaration_id')->constrained('conflict_of_interest_declarations')->cascadeOnDelete();
                $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20); // clear | potential_conflict
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['declaration_id', 'submission_id'], 'uniq_declaration_submission_response');
                $table->index('status', 'idx_coi_responses_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_of_interest_responses');

        if ($this->foreignKeyExists('coi_decl_invitation_fk')) {
            Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
                $table->dropForeign('coi_decl_invitation_fk');
            });
        }

        Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
            foreach (['reviewer_round_invitation_id', 'admin_notified_at', 'superseded_at'] as $column) {
                if (Schema::hasColumn('conflict_of_interest_declarations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function foreignKeyExists(string $name): bool
    {
        return collect(Schema::getForeignKeys('conflict_of_interest_declarations'))
            ->contains(fn ($foreignKey) => is_array($foreignKey)
                ? ($foreignKey['name'] ?? null) === $name
                : $foreignKey->getName() === $name);
    }

    private function indexExists(string $name): bool
    {
        return collect(Schema::getIndexes('conflict_of_interest_declarations'))
            ->contains(fn ($index) => is_array($index)
                ? ($index['name'] ?? null) === $name
                : $index->getName() === $name);
    }
};
