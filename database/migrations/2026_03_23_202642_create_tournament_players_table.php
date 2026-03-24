<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('faction')->nullable();
            $table->boolean('is_ringer')->default(false);
            $table->boolean('is_disqualified')->default(false);
            $table->dateTime('disqualified_at')->nullable();
            $table->unsignedTinyInteger('dropped_after_round')->nullable();
            $table->timestamps();

            $table->index('tournament_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_players');
    }
};
