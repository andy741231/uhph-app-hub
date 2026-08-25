<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen peoplesoft_id to accommodate 7+ digit IDs
        Schema::table('users', function (Blueprint $table): void {
            $table->string('peoplesoft_id', 20)->nullable()->change();
        });

        // Null out old investigator_type values before changing the enum
        DB::table('users')->whereNotNull('investigator_type')->update(['investigator_type' => null]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN; the column was created with
            // the old enum in the base migration. Recreate it with the new check.
            // SQLite stores enums as plain strings, so we just need to ensure
            // the column accepts the new values — no schema change needed.
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN investigator_type ENUM('pi', 'other') NULL");
        }

        // Add boolean checkboxes for investigator categories
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('early_stage_investigator')->default(false)->after('investigator_type');
            $table->boolean('new_investigator')->default(false)->after('early_stage_investigator');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['early_stage_investigator', 'new_investigator']);
        });

        DB::table('users')->whereNotNull('investigator_type')->update(['investigator_type' => null]);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN investigator_type ENUM('early_stage', 'new') NULL");
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('peoplesoft_id', 6)->nullable()->change();
        });
    }
};
