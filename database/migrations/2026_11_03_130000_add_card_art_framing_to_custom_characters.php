<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the chosen library art is framed inside the card's art window: the focus point (0-100% across/down the
 * image) and a zoom percentage (100 = fill the window, up to 300).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->unsignedTinyInteger('card_art_x')->default(50)->after('card_art_id');
            $table->unsignedTinyInteger('card_art_y')->default(50)->after('card_art_x');
            $table->unsignedSmallInteger('card_art_zoom')->default(100)->after('card_art_y');
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn(['card_art_x', 'card_art_y', 'card_art_zoom']);
        });
    }
};
