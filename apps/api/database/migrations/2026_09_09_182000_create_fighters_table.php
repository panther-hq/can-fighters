<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fighters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('primary_class');
            $table->string('secondary_class')->nullable();
            $table->string('rarity');
            $table->string('personality')->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->json('traits');
            $table->json('visual_dna');
            $table->json('suggested_skills');
            $table->unsignedBigInteger('generation_seed');
            $table->unsignedInteger('generation_version');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fighters');
    }
};
