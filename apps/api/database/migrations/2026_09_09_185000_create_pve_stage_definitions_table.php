<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pve_stage_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('region');
            $table->string('name');
            $table->unsignedInteger('order');
            $table->boolean('is_boss')->default(false);
            $table->unsignedInteger('enemy_budget'); // per-enemy Power Budget
            $table->json('enemies'); // [{ name, class, position }]
            $table->json('rewards'); // { coins, xp, fighterXp, ingredientDrops:[{slug,chance,min,max}], canDrops:[{slug,chance}] }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pve_stage_definitions');
    }
};
