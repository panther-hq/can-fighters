<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // pve | arena
            $table->unsignedBigInteger('seed');
            $table->unsignedInteger('battle_version');
            $table->foreignId('player_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_b_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage_slug')->nullable();
            $table->string('winner'); // A | B | draw
            $table->json('result'); // full BattleResult
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('battle_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->string('team'); // A | B
            $table->json('combatants'); // the CombatantInput[] used
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_snapshots');
        Schema::dropIfExists('battles');
    }
};
