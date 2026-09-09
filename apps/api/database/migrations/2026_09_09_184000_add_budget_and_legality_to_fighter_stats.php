<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fighter_stats', function (Blueprint $table) {
            $table->unsignedInteger('budget')->default(0)->after('power_score');
            $table->boolean('pvp_legal')->default(true)->after('budget');
        });
    }

    public function down(): void
    {
        Schema::table('fighter_stats', function (Blueprint $table) {
            $table->dropColumn(['budget', 'pvp_legal']);
        });
    }
};
