<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leaders and totems are CustomCharacter rows, not CampaignArsenalModel rows,
     * but they can be killed in campaign games and must flip for injuries (pg 34–36).
     * Add custom_character_id (nullable FK) so leader/totem injuries can be stored
     * in the same table. campaign_arsenal_model_id becomes nullable to accommodate:
     * each injury row has exactly one of (campaign_arsenal_model_id, custom_character_id).
     */
    public function up(): void
    {
        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->dropForeignSafe('fk_camim_arsenal_model');
        });

        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_arsenal_model_id')->nullable()->change();
            $table->foreign('campaign_arsenal_model_id', 'fk_camim_arsenal_model')
                ->references('id')->on('campaign_arsenal_models')
                ->cascadeOnDelete();
            $table->foreignId('custom_character_id')->nullable()->after('campaign_arsenal_model_id')
                ->constrained('custom_characters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->dropForeignSafe(['custom_character_id']);
            $table->dropColumn('custom_character_id');
        });

        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->dropForeignSafe('fk_camim_arsenal_model');
        });

        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_arsenal_model_id')->nullable(false)->change();
            $table->foreign('campaign_arsenal_model_id', 'fk_camim_arsenal_model')
                ->references('id')->on('campaign_arsenal_models')
                ->cascadeOnDelete();
        });
    }
};
