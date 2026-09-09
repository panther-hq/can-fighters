<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('can_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('can_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seed');
            // [ { "slug": "pasta", "quantity": 2 }, ... ]
            $table->json('results');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('can_openings');
    }
};
