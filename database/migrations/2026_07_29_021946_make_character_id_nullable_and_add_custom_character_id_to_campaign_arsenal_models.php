<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hired arsenal model is almost always an official Character catalog
     * row, but the crew owner can also hire one of their own homebrew
     * CustomCharacter cards (Card Creator). Mirrors the identical
     * campaign_arsenal_model_injuries dual-key pattern from
     * 2026_07_01_130000_allow_leader_totem_injuries.php: character_id becomes
     * nullable, custom_character_id is added — exactly one of the two is set.
     */
    public function up(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropForeignSafe('campaign_arsenal_models_character_id_foreign');
        });

        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->nullable()->change();
            $table->foreign('character_id')
                ->references('id')->on('characters')
                ->cascadeOnDelete();
            $table->foreignId('custom_character_id')->nullable()->after('character_id')
                ->constrained('custom_characters')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropForeignSafe(['custom_character_id']);
            $table->dropColumn('custom_character_id');
        });

        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropForeignSafe('campaign_arsenal_models_character_id_foreign');
        });

        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->nullable(false)->change();
            $table->foreign('character_id')
                ->references('id')->on('characters')
                ->cascadeOnDelete();
        });
    }
};
