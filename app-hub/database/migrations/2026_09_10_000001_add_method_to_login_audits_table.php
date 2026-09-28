<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_audits', function (Blueprint $table) {
            $table->string('method', 10)->default('password')->after('succeeded');
        });
    }

    public function down(): void
    {
        Schema::table('login_audits', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
