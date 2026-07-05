<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Back-Alley Doctor (pg 33) couldn't target leader/totem injuries —
     * only CampaignArsenalModel rows. Leaders/totems are CustomCharacter rows.
     * Mirrors 2026_07_01_130000_allow_leader_totem_injuries.php: add a nullable
     * target_custom_character_id FK and make target_arsenal_model_id nullable,
     * so each log row has exactly one of the two targets set.
     */
    public function up(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->dropForeign(['target_arsenal_model_id']);
        });

        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->unsignedBigInteger('target_arsenal_model_id')->nullable()->change();
            $table->foreign('target_arsenal_model_id')
                ->references('id')->on('campaign_arsenal_models')
                ->cascadeOnDelete();
            $table->foreignId('target_custom_character_id')->nullable()->after('target_arsenal_model_id')
                ->constrained('custom_characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->dropForeignSafe(['target_custom_character_id']);
            $table->dropColumn('target_custom_character_id');
        });

        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->dropForeignSafe(['target_arsenal_model_id']);
        });

        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->unsignedBigInteger('target_arsenal_model_id')->nullable(false)->change();
            $table->foreign('target_arsenal_model_id')
                ->references('id')->on('campaign_arsenal_models')
                ->cascadeOnDelete();
        });
    }
};
