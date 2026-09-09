<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedBigInteger('coins')->default(0);
            $table->unsignedInteger('rating')->default(1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_profiles');
    }
};
