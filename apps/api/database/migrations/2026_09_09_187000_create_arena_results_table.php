<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attacker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('defender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->boolean('attacker_won');
            $table->unsignedInteger('attacker_rating_before');
            $table->unsignedInteger('attacker_rating_after');
            $table->unsignedInteger('defender_rating_before');
            $table->unsignedInteger('defender_rating_after');
            $table->timestamp('created_at')->nullable();

            $table->index(['attacker_id', 'id']);
            $table->index(['defender_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_results');
    }
};
