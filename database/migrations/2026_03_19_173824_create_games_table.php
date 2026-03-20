<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('setup');
            $table->unsignedInteger('encounter_size');
            $table->string('season');
            $table->foreignId('strategy_id')->nullable()->constrained('strategies')->nullOnDelete();
            $table->string('deployment')->nullable();
            $table->json('scheme_pool')->nullable();
            $table->unsignedTinyInteger('current_turn')->default(0);
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('creator_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
