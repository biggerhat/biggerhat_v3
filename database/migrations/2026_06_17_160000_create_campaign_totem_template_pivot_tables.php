<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Totem templates (CustomCharacter rows flagged is_campaign_totem_template) can
 * link existing Actions and Abilities — and flag which actions are signature —
 * the same way Crew Cards do. Pivots reference custom_characters; only template
 * rows populate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_totem_template_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_character_id');
            $table->unsignedBigInteger('action_id');
            $table->boolean('is_signature_action')->default(false);
            $table->primary(['custom_character_id', 'action_id'], 'ctt_action_pk');
            $table->foreign('custom_character_id', 'ctt_action_char_fk')
                ->references('id')->on('custom_characters')->cascadeOnDelete();
            $table->foreign('action_id', 'ctt_action_action_fk')
                ->references('id')->on('actions')->cascadeOnDelete();
        });

        Schema::create('campaign_totem_template_abilities', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_character_id');
            $table->unsignedBigInteger('ability_id');
            $table->primary(['custom_character_id', 'ability_id'], 'ctt_ability_pk');
            $table->foreign('custom_character_id', 'ctt_ability_char_fk')
                ->references('id')->on('custom_characters')->cascadeOnDelete();
            $table->foreign('ability_id', 'ctt_ability_ability_fk')
                ->references('id')->on('abilities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_totem_template_actions');
        Schema::dropIfExists('campaign_totem_template_abilities');
    }
};
