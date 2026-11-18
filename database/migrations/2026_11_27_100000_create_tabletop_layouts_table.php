<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terrain setups people save from the tabletop sandbox or from a game board, to load again later or to share by link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabletop_layouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            // The terrain pieces exactly as the board keeps them (see UpdateTerrainRequest), and the table surface chosen with them.
            $table->json('terrain');
            $table->string('table_image', 60)->nullable();
            // Anyone with the link may look at it and save a copy of their own.
            $table->boolean('is_shared')->default(false);
            // Where a copy came from: the layout's uuid, or the game's, so a setup can be traced back. Not a foreign key: the source may be deleted.
            $table->string('copied_from', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at'], 'tabletop_layouts_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabletop_layouts');
    }
};
