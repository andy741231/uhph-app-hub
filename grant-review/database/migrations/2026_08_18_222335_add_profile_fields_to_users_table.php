<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('department');
            $table->string('title')->nullable()->after('phone');
            $table->string('peoplesoft_id', 6)->nullable()->after('title');
            $table->enum('investigator_type', ['early_stage', 'new'])->nullable()->after('peoplesoft_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'title', 'peoplesoft_id', 'investigator_type']);
        });
    }
};
