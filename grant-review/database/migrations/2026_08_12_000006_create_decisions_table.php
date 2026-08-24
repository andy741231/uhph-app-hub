<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('outcome', ['funded', 'not_funded']);
            $table->decimal('amount_awarded', 12, 2)->nullable();
            $table->foreignId('decided_by')->constrained('users'); // admin user id
            $table->timestamp('decided_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
