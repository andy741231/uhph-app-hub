<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('frontchannel_logout_path')->nullable()->after('callback_url');
        });

        DB::table('applications')->where('key', 'grant-review')->update([
            'frontchannel_logout_path' => '/apps/grant-review/auth/hub/logout',
        ]);
        DB::table('applications')->where('key', 'flipbook')->update([
            'frontchannel_logout_path' => '/apps/flipbook/auth/hub-logout.php',
        ]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('frontchannel_logout_path');
        });
    }
};
