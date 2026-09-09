<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('can_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');       // Polish display name
            $table->string('icon', 16);
            $table->string('rarity');
            // Drop table: { "rolls": 3, "weights": { "<ingredient_slug>": <int>, ... } }
            $table->json('drops');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('can_definitions');
    }
};
