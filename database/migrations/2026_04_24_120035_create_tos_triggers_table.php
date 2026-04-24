<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            // Suit symbols / margin requirements per rulebook p. 25:
            // "Triggers allow a unit to change the results of an Action based
            // upon the suits present in its final duel total or the amount by
            // which it exceeded its target's defenses."
            $table->string('suits', 32)->nullable();
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['action_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_triggers');
    }
};
