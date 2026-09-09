<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fighter_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fighter_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('hp')->default(0);
            $table->unsignedInteger('attack')->default(0);
            $table->unsignedInteger('defense')->default(0);
            $table->unsignedInteger('magic')->default(0);
            $table->unsignedInteger('speed')->default(0);
            $table->unsignedInteger('crit')->default(0); // percent
            $table->unsignedInteger('power_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fighter_stats');
    }
};
