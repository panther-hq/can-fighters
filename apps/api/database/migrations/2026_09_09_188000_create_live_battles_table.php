<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_b_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            $table->string('status')->default('active'); // active | finished
            $table->unsignedInteger('round')->default(1);
            $table->string('winner')->nullable(); // A | B | draw
            $table->json('state');
            $table->json('pending'); // { "<playerId>": { type, slot?, targetId? } }
            $table->timestamp('round_opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_battles');
    }
};
