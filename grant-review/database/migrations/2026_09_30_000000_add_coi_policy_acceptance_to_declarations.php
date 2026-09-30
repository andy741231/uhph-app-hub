<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit snapshot of the COI Policy & Guidelines acceptance on each
     * declaration: the canonical document version, the exact wording
     * the reviewer agreed to, and when it was acknowledged.
     *
     * Columns stay nullable — declarations recorded before this release
     * have no captured acceptance and must render as "not recorded"
     * rather than assuming today's wording.
     *
     * Guarded so the migration is safe to re-run after a partially
     * applied DDL attempt (MySQL does not roll back DDL).
     */
    public function up(): void
    {
        Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
            if (! Schema::hasColumn('conflict_of_interest_declarations', 'coi_policy_version')) {
                $table->string('coi_policy_version', 20)->nullable()->after('superseded_at');
            }
            if (! Schema::hasColumn('conflict_of_interest_declarations', 'coi_policy_content')) {
                $table->longText('coi_policy_content')->nullable()->after('coi_policy_version');
            }
            if (! Schema::hasColumn('conflict_of_interest_declarations', 'coi_policy_acknowledged_at')) {
                $table->timestamp('coi_policy_acknowledged_at')->nullable()->after('coi_policy_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conflict_of_interest_declarations', function (Blueprint $table) {
            foreach (['coi_policy_version', 'coi_policy_content', 'coi_policy_acknowledged_at'] as $column) {
                if (Schema::hasColumn('conflict_of_interest_declarations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
