<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fighter_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->string('skill_family');
            $table->string('modifier')->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->json('parameters'); // filled by the Balance Engine
            $table->timestamps();

            $table->unique(['fighter_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fighter_skills');
    }
};
