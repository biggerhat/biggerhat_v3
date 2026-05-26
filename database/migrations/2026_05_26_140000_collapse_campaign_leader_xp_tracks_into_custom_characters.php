<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collapses the 1:1 `campaign_leader_xp_tracks` table — whose only payload was
 * a single JSON column — into an `xp_track` column on `custom_characters`. The
 * canonical 27-box layout now lives on `CustomCharacter::defaultXpTrack()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->json('xp_track')->nullable()->after('current');
        });

        Schema::dropIfExists('campaign_leader_xp_tracks');
    }

    public function down(): void
    {
        Schema::create('campaign_leader_xp_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_character_id')->constrained()->cascadeOnDelete();
            $table->json('track');
            $table->timestamps();
        });

        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn('xp_track');
        });
    }
};
