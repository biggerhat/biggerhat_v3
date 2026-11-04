<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free offsets (percent of the art window, negative = left/up) on top of the cover-focus pan, so the art can be
     * pushed past the window edges on either axis.
     */
    public function up(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->smallInteger('card_art_dx')->default(0)->after('card_art_zoom');
            $table->smallInteger('card_art_dy')->default(0)->after('card_art_dx');
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn(['card_art_dx', 'card_art_dy']);
        });
    }
};
