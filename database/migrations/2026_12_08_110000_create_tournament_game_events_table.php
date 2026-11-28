<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_game_id')->constrained()->cascadeOnDelete();
            // Who changed it; kept as null if the account is deleted, so the history still reads.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tournament_game_id', 'created_at'], 'tge_game_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_game_events');
    }
};
