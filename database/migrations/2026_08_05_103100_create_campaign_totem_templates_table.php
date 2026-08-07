<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Totem Templates (pg 52 — Spirit Familiar / Sniveling Coward / Mini Master,
 * any Leader's generic Totem Advancement pick) were `custom_characters` rows
 * flagged `is_campaign_totem_template`, owned via `user_id` by whichever
 * admin created them. Two real problems with that: (1) nothing distinguished
 * a template from that admin's own personal cards, so it could be deleted
 * through the generic Card Creator "My Custom Characters" flow with zero
 * protection; (2) `custom_characters.user_id` is a non-nullable
 * `cascadeOnDelete()` FK — deleting that admin's account would silently wipe
 * every template they created, a raw DB constraint no app code could catch.
 *
 * Moves templates to their own ownerless catalog table — no `user_id` at
 * all — mirroring how `campaign_crew_cards` already works. Only the
 * template (the catalog source) moves; a crew's actual hired Totem stays a
 * `custom_characters` row cloned by value from the template (see
 * LeaderAdvancementService::createTotemFromTemplate()) and is completely
 * unaffected by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_totem_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('faction')->nullable();
            $table->string('station')->nullable();
            $table->unsignedTinyInteger('cost')->nullable();
            $table->unsignedTinyInteger('health');
            $table->unsignedTinyInteger('defense');
            $table->string('defense_suit')->nullable();
            $table->unsignedTinyInteger('willpower');
            $table->string('willpower_suit')->nullable();
            $table->unsignedTinyInteger('speed');
            $table->unsignedTinyInteger('size')->nullable();
            $table->string('base')->default('30');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('campaign_totem_flip_value')->nullable();
            $table->boolean('campaign_is_black_joker_totem')->default(false);
            $table->boolean('campaign_is_red_joker_totem')->default(false);
            $table->boolean('campaign_totem_special_replace')->default(false);
            $table->boolean('campaign_is_mini_master')->default(false);
            $table->timestamps();
        });

        // Temp names — the final campaign_totem_template_actions/abilities
        // names are still held by the old custom_character_id-keyed pivots
        // until they're dropped below.
        Schema::create('campaign_totem_template_actions_new', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_totem_template_id');
            $table->unsignedBigInteger('action_id');
            $table->boolean('is_signature_action')->default(false);
            $table->primary(['campaign_totem_template_id', 'action_id'], 'ctt2_action_pk');
            $table->foreign('campaign_totem_template_id', 'ctt2_action_tmpl_fk')
                ->references('id')->on('campaign_totem_templates')->cascadeOnDelete();
            $table->foreign('action_id', 'ctt2_action_action_fk')
                ->references('id')->on('actions')->cascadeOnDelete();
        });

        Schema::create('campaign_totem_template_abilities_new', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_totem_template_id');
            $table->unsignedBigInteger('ability_id');
            $table->primary(['campaign_totem_template_id', 'ability_id'], 'ctt2_ability_pk');
            $table->foreign('campaign_totem_template_id', 'ctt2_ability_tmpl_fk')
                ->references('id')->on('campaign_totem_templates')->cascadeOnDelete();
            $table->foreign('ability_id', 'ctt2_ability_ability_fk')
                ->references('id')->on('abilities')->cascadeOnDelete();
        });

        $this->migrateExistingTemplates();

        Schema::dropIfExists('campaign_totem_template_actions');
        Schema::dropIfExists('campaign_totem_template_abilities');
        Schema::rename('campaign_totem_template_actions_new', 'campaign_totem_template_actions');
        Schema::rename('campaign_totem_template_abilities_new', 'campaign_totem_template_abilities');
    }

    public function down(): void
    {
        Schema::create('campaign_totem_template_actions_old', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_character_id');
            $table->unsignedBigInteger('action_id');
            $table->boolean('is_signature_action')->default(false);
            $table->primary(['custom_character_id', 'action_id'], 'ctt_action_pk');
            $table->foreign('custom_character_id', 'ctt_action_char_fk')
                ->references('id')->on('custom_characters')->cascadeOnDelete();
            $table->foreign('action_id', 'ctt_action_action_fk')
                ->references('id')->on('actions')->cascadeOnDelete();
        });

        Schema::create('campaign_totem_template_abilities_old', function (Blueprint $table) {
            $table->unsignedBigInteger('custom_character_id');
            $table->unsignedBigInteger('ability_id');
            $table->primary(['custom_character_id', 'ability_id'], 'ctt_ability_pk');
            $table->foreign('custom_character_id', 'ctt_ability_char_fk')
                ->references('id')->on('custom_characters')->cascadeOnDelete();
            $table->foreign('ability_id', 'ctt_ability_ability_fk')
                ->references('id')->on('abilities')->cascadeOnDelete();
        });

        foreach (DB::table('campaign_totem_templates')->get() as $t) {
            $newId = DB::table('custom_characters')->insertGetId([
                'user_id' => DB::table('users')->value('id'),
                'share_code' => \Illuminate\Support\Str::random(12),
                'name' => $t->name,
                'title' => $t->title,
                'display_name' => $t->name,
                'slug' => \Illuminate\Support\Str::slug($t->name).'-'.$t->id,
                'faction' => $t->faction,
                'station' => $t->station,
                'cost' => $t->cost,
                'health' => $t->health,
                'defense' => $t->defense,
                'defense_suit' => $t->defense_suit,
                'willpower' => $t->willpower,
                'willpower_suit' => $t->willpower_suit,
                'speed' => $t->speed,
                'size' => $t->size,
                'base' => $t->base,
                'notes' => $t->notes,
                'is_campaign_totem_template' => true,
                'campaign_totem_flip_value' => $t->campaign_totem_flip_value,
                'campaign_is_black_joker_totem' => $t->campaign_is_black_joker_totem,
                'campaign_is_red_joker_totem' => $t->campaign_is_red_joker_totem,
                'campaign_totem_special_replace' => $t->campaign_totem_special_replace,
                'campaign_is_mini_master' => $t->campaign_is_mini_master,
                'created_at' => $t->created_at,
                'updated_at' => $t->updated_at,
            ]);

            foreach (DB::table('campaign_totem_template_actions')->where('campaign_totem_template_id', $t->id)->get() as $row) {
                DB::table('campaign_totem_template_actions_old')->insert([
                    'custom_character_id' => $newId,
                    'action_id' => $row->action_id,
                    'is_signature_action' => $row->is_signature_action,
                ]);
            }
            foreach (DB::table('campaign_totem_template_abilities')->where('campaign_totem_template_id', $t->id)->get() as $row) {
                DB::table('campaign_totem_template_abilities_old')->insert([
                    'custom_character_id' => $newId,
                    'ability_id' => $row->ability_id,
                ]);
            }
        }

        Schema::dropIfExists('campaign_totem_template_actions');
        Schema::dropIfExists('campaign_totem_template_abilities');
        Schema::rename('campaign_totem_template_actions_old', 'campaign_totem_template_actions');
        Schema::rename('campaign_totem_template_abilities_old', 'campaign_totem_template_abilities');

        Schema::dropIfExists('campaign_totem_templates');
    }

    private function migrateExistingTemplates(): void
    {
        $templates = DB::table('custom_characters')->where('is_campaign_totem_template', true)->get();

        foreach ($templates as $t) {
            // Preserves the old custom_characters.id as the new row's id —
            // campaign_leader_advancements.advancement_catalog_id historically
            // stored that id for source_table=Totem rows (see
            // AftermathCatalog::advancementDisplayName()), and there's no
            // migration step that rewrites those historical log rows. Keeping
            // the id stable means past Aftermath history keeps resolving the
            // totem's name correctly instead of going blank.
            $newId = DB::table('campaign_totem_templates')->insertGetId([
                'id' => $t->id,
                'name' => $t->name,
                'title' => $t->title,
                'faction' => $t->faction,
                'station' => $t->station,
                'cost' => $t->cost,
                'health' => $t->health,
                'defense' => $t->defense,
                'defense_suit' => $t->defense_suit,
                'willpower' => $t->willpower,
                'willpower_suit' => $t->willpower_suit,
                'speed' => $t->speed,
                'size' => $t->size,
                'base' => $t->base,
                'notes' => $t->notes,
                'campaign_totem_flip_value' => $t->campaign_totem_flip_value,
                'campaign_is_black_joker_totem' => $t->campaign_is_black_joker_totem,
                'campaign_is_red_joker_totem' => $t->campaign_is_red_joker_totem,
                'campaign_totem_special_replace' => $t->campaign_totem_special_replace,
                'campaign_is_mini_master' => $t->campaign_is_mini_master,
                'created_at' => $t->created_at,
                'updated_at' => $t->updated_at,
            ]);

            foreach (DB::table('campaign_totem_template_actions')->where('custom_character_id', $t->id)->get() as $row) {
                DB::table('campaign_totem_template_actions_new')->insert([
                    'campaign_totem_template_id' => $newId,
                    'action_id' => $row->action_id,
                    'is_signature_action' => $row->is_signature_action,
                ]);
            }
            foreach (DB::table('campaign_totem_template_abilities')->where('custom_character_id', $t->id)->get() as $row) {
                DB::table('campaign_totem_template_abilities_new')->insert([
                    'campaign_totem_template_id' => $newId,
                    'ability_id' => $row->ability_id,
                ]);
            }
        }

        DB::table('custom_characters')->where('is_campaign_totem_template', true)->delete();
    }
};
