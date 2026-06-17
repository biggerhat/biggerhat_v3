<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_crew_card_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_crew_card_id');
            $table->unsignedBigInteger('action_id');
            $table->primary(['campaign_crew_card_id', 'action_id'], 'ccc_action_pk');
            $table->foreign('campaign_crew_card_id', 'ccc_action_card_fk')
                ->references('id')->on('campaign_crew_cards')->cascadeOnDelete();
            $table->foreign('action_id', 'ccc_action_action_fk')
                ->references('id')->on('actions')->cascadeOnDelete();
        });

        Schema::create('campaign_crew_card_abilities', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_crew_card_id');
            $table->unsignedBigInteger('ability_id');
            $table->primary(['campaign_crew_card_id', 'ability_id'], 'ccc_ability_pk');
            $table->foreign('campaign_crew_card_id', 'ccc_ability_card_fk')
                ->references('id')->on('campaign_crew_cards')->cascadeOnDelete();
            $table->foreign('ability_id', 'ccc_ability_ability_fk')
                ->references('id')->on('abilities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_crew_card_actions');
        Schema::dropIfExists('campaign_crew_card_abilities');
    }
};
