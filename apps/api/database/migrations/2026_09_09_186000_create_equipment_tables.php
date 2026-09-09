<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');       // Polish display name
            $table->string('icon', 16);
            $table->string('slot');       // weapon | armor | accessory
            $table->string('rarity');
            // { hp, attack, defense, magic, speed, crit } — flat bonuses at rarity "common"
            $table->json('base_stats');
            $table->string('special')->nullable(); // flavour effect name
            $table->timestamps();
        });

        Schema::create('player_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_definition_id')->constrained()->cascadeOnDelete();
            $table->string('rarity');
            $table->unsignedBigInteger('seed');
            $table->json('rolled_stats'); // final flat bonuses after the roll
            $table->timestamps();
        });

        Schema::create('fighter_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_equipment_id')->constrained('player_equipment')->cascadeOnDelete();
            $table->string('slot');
            $table->timestamps();

            $table->unique(['fighter_id', 'slot']);
            $table->unique('player_equipment_id'); // a piece is worn by at most one fighter
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fighter_equipment');
        Schema::dropIfExists('player_equipment');
        Schema::dropIfExists('equipment_definitions');
    }
};
