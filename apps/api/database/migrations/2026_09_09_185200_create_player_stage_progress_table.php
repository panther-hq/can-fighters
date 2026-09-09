<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stage_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage_slug');
            $table->unsignedTinyInteger('stars')->default(0);
            $table->foreignId('best_battle_id')->nullable()->constrained('battles')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'stage_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stage_progress');
    }
};
