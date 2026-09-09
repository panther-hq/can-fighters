<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');            // Polish display name
            $table->string('icon', 16);        // emoji
            $table->string('rarity');          // common | uncommon | rare | epic | legendary
            $table->unsignedSmallInteger('power_value');
            $table->json('tags');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_definitions');
    }
};
