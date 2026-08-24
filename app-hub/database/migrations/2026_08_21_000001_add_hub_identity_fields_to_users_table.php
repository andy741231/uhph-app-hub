<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('public_id')->unique()->after('id');
            $table->string('external_subject')->nullable()->unique()->after('email');
            $table->string('status', 20)->default('active')->index()->after('password');
            $table->boolean('is_admin')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropUnique(['external_subject']);
            $table->dropIndex(['status']);
            $table->dropColumn(['public_id', 'external_subject', 'status', 'is_admin', 'last_login_at']);
        });
    }
};
