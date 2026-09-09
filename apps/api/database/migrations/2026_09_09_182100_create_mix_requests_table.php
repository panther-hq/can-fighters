<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mix_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            $table->string('status')->default('pending'); // pending|processing|completed|failed
            $table->unsignedInteger('generation_version');
            $table->json('input'); // [{ "slug": "...", "quantity": n }, ...]
            $table->foreignId('result_fighter_id')->nullable()->constrained('fighters')->nullOnDelete();
            $table->string('error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mix_requests');
    }
};
