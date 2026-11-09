<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cooperative Mode non-player (NP) class cards (Index of the Untold pp. 132-137). The card is read as a unit,
     * so the abilities / actions / behavior chart are JSON rather than child tables.
     */
    public function up(): void
    {
        Schema::create('coop_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role', 20)->index();
            $table->boolean('is_unique')->default(false);
            $table->unsignedTinyInteger('resistance');
            $table->unsignedTinyInteger('speed')->nullable();
            $table->unsignedTinyInteger('max_health');
            $table->unsignedTinyInteger('elite_max_health')->nullable();
            $table->json('abilities')->nullable();
            $table->json('actions')->nullable();
            $table->json('behavior')->nullable();
            $table->json('target_preference')->nullable();
            $table->text('elite_modifications')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_classes');
    }
};
