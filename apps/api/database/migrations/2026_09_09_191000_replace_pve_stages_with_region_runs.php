<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('player_stage_progress');
        Schema::dropIfExists('pve_stage_definitions');

        Schema::create('region_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('order');
            $table->unsignedInteger('enemy_budget')->default(70);
            $table->json('enemy_pool');  // [{ name, class }]
            $table->json('boss');        // { name, class }
            $table->json('drops');       // { ingredients: [...], cans: [...], equipment: [...] }
            $table->timestamps();
        });

        Schema::create('player_region_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('region_slug');
            $table->unsignedBigInteger('seed');
            $table->json('map');                        // { width, height, terrain[], start, objects[] }
            $table->unsignedInteger('hero_x');
            $table->unsignedInteger('hero_y');
            $table->unsignedInteger('movement_left');
            $table->unsignedInteger('movement_max');
            $table->unsignedInteger('day')->default(1);
            $table->json('revealed');                   // ["x,y", ...] fog-of-war uncovered tiles
            $table->json('resolved_object_ids');        // ids of enemies/treasure/events already cleared
            $table->json('active_merchant')->nullable(); // { objectId, offers[] }
            $table->string('status')->default('active'); // active | cleared | abandoned
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('player_region_clears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('region_slug');
            $table->unsignedInteger('times_cleared')->default(0);
            $table->timestamp('first_cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'region_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_region_clears');
        Schema::dropIfExists('player_region_runs');
        Schema::dropIfExists('region_definitions');
    }
};
