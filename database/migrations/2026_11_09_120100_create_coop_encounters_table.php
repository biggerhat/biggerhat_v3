<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cooperative Mode encounters (Index of the Untold pp. 80-131). Number 0 is the introduction. */
    public function up(): void
    {
        Schema::create('coop_encounters', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('narrative')->nullable();
            $table->unsignedTinyInteger('encounter_size')->nullable();
            $table->unsignedTinyInteger('max_hand_size')->nullable();
            $table->string('board_size', 40)->nullable();
            $table->text('challenge_mode')->nullable();
            $table->json('phases')->nullable();
            $table->json('campaign')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_encounters');
    }
};
