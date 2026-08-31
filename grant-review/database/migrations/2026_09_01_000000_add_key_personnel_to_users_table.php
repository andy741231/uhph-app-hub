<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store key personnel (title + name pairs) as a JSON array on the users
     * table so submitters can list additional team members on their profile.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('key_personnel')->nullable()->after('new_investigator');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('key_personnel');
        });
    }
};
