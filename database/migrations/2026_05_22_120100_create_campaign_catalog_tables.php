<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4E Campaign Mode — catalog (rulebook reference data) tables.
 *
 * Each table represents one of the rulebook charts: archetypes (pg 17),
 * crew-card starter effects (pg 15–16), the 82-piece equipment catalog
 * (pg 22–28) + Those Who Thirst (pg 29–30) + Omen's Mark, the injury
 * table (pg 34–35), Lucky Miss (pg 36), Back-Alley Doctor (pg 33), all
 * leader advancement tables (pg 38–54), totems (pg 52–53), summoning
 * advancements (pg 54), and weekly events (pg 148–149).
 *
 * Rows are seeded empty here. Hand-data-entry happens via admin CRUD
 * in Phase 1 of the build plan.
 *
 * These tables are NOT scoped via game_mode_type — they have no
 * standard-mode sibling rows and live entirely in the campaign domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 5 rows. The Leader Builder reads caps + stat baselines from here.
        Schema::create('leader_archetypes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // LeaderArchetypeEnum value
            $table->string('name');
            $table->unsignedTinyInteger('df');
            $table->unsignedTinyInteger('wp');
            $table->unsignedTinyInteger('sp');
            $table->unsignedTinyInteger('health');
            $table->unsignedTinyInteger('attack_actions_count');
            $table->unsignedTinyInteger('attack_action_cost_cap');
            // Heavy Hitter only: chosen attack action comes with one of its triggers.
            $table->boolean('attack_gets_trigger')->default(false);
            $table->unsignedTinyInteger('tactical_actions_count');
            $table->unsignedTinyInteger('tactical_action_cost_cap');
            $table->unsignedTinyInteger('abilities_count');
            $table->unsignedTinyInteger('ability_cost_cap');
            // Lucky Upstart starter equipment flip etc.
            $table->text('special_notes')->nullable();
            $table->timestamps();
        });

        // 13 starter effects (pg 15–16). Tier-4 borrows also reference this.
        Schema::create('crew_card_effects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->boolean('requires_token_choice')->default(false);
            $table->boolean('requires_marker_choice')->default(false);
            $table->boolean('requires_upgrade_type_choice')->default(false);
            // Most effects gate on non-peon; some specify "only leader" etc.
            $table->json('restrictions')->nullable();
            $table->json('grants_ability')->nullable(); // { name, body, suits, defensive_type }
            $table->json('grants_action')->nullable();  // { rg, skl, rst, tn, dmg, ... }
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 82 entries + 8 Those Who Thirst + Omen's Mark + 4 Always-Available.
        Schema::create('equipment_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Most rows store a numeric br (1..13). 'Always Available' rows
            // use is_always_available; Those Who Thirst red-joker entry uses
            // is_red_joker_entry. Storing both makes querying simple.
            $table->unsignedTinyInteger('br')->nullable();
            $table->unsignedTinyInteger('cc'); // campaign cost in scrip
            $table->boolean('is_always_available')->default(false);
            $table->boolean('is_red_joker_entry')->default(false);
            $table->boolean('ttw_only')->default(false);
            $table->boolean('is_omens_mark')->default(false);
            // Suit pool eligibility — equipment lists e.g. "1 of {{ram}} or {{tome}}";
            // both suits must be present in the row so the barter query can
            // match against crew keyword suit pools.
            $table->string('pool_suit_a')->nullable();
            $table->string('pool_suit_b')->nullable();
            $table->boolean('is_unique')->default(false);
            $table->boolean('leader_only')->default(false);
            $table->boolean('non_unique_only')->default(false);
            // Loot Their Stash + a few others annihilate after the game.
            $table->boolean('annihilate_after_game')->default(false);
            $table->text('body');
            $table->json('granted_ability')->nullable();
            $table->json('granted_action')->nullable();
            $table->timestamps();
            $table->index('br');
            $table->index(['pool_suit_a', 'pool_suit_b']);
        });

        // 13 × 2 suits + 4 jokers = 30 rows.
        Schema::create('injury_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->unsignedTinyInteger('flip_value')->nullable();
            // pc | te | black_joker | red_joker. The two-suit polarity matches
            // the rulebook's stacked tables.
            $table->string('suit_pool');
            $table->boolean('reflip_if_no_triggers')->default(false); // Permanent Hex
            $table->boolean('reflip_if_master_or_totem')->default(false); // Headstrong / Traitor
            $table->boolean('is_traitor')->default(false); // Black Joker
            $table->boolean('is_close_call')->default(false); // Red Joker
            $table->boolean('annihilates_model')->default(false); // Killed Off
            $table->timestamps();
            $table->index(['suit_pool', 'flip_value']);
        });

        // 13 entries + Doppelganger joker.
        Schema::create('lucky_miss_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->unsignedTinyInteger('flip_value')->nullable();
            $table->boolean('is_doppelganger')->default(false);
            $table->timestamps();
        });

        // 8 entries spanning BJ / 1-8 / 9 / 10 / 11 / 12-13 / RJ.
        Schema::create('back_alley_doctor_results', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->unsignedTinyInteger('flip_value_min')->nullable();
            $table->unsignedTinyInteger('flip_value_max')->nullable();
            $table->boolean('is_black_joker')->default(false);
            $table->boolean('is_red_joker')->default(false);
            // no_effect | removed | added_injury | gained_undead | gained_construct | lucky_miss_reflip
            $table->string('outcome_kind');
            $table->timestamps();
        });

        // Shared shape for the four flip-based advancement tables.
        // Each row stores everything needed to render + apply the result.
        $advancementColumns = function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->unsignedTinyInteger('flip_value')->nullable();
            $table->boolean('is_always_available')->default(false);
            $table->boolean('is_black_joker')->default(false);
            $table->boolean('is_red_joker')->default(false);
            // For attack/tactical mod tables: trigger | skl | signature | joker
            // For action/ability tables: typically 'choice' with optional joker variant
            $table->string('modifier_type');
            // SuitEnum value for trigger advancements (P/C/T/E/{{signature}}).
            $table->string('suit')->nullable();
            // For Skl-boost rows on attack/tactical mod tables.
            $table->unsignedTinyInteger('skl_from')->nullable();
            $table->unsignedTinyInteger('skl_to')->nullable();
            // Signature row converts the target action into a signature action.
            $table->boolean('grants_signature')->default(false);
            // Joker variants: pick freely (Choose any action / ability on a non-
            // master / non-totem model sharing keyword with leader, cost ≤ 10).
            $table->boolean('joker_freechoice')->default(false);
            // For action-table rows: the full stat block of the new action.
            $table->json('stat_block')->nullable();
            // For ability rows that introduce defensive abilities.
            $table->string('defensive_ability_type')->nullable();
            $table->timestamps();
            $table->index(['modifier_type', 'flip_value']);
        };

        Schema::create('advancement_attack_mod', $advancementColumns);
        Schema::create('advancement_tactical_mod', $advancementColumns);
        Schema::create('advancement_action', $advancementColumns);
        Schema::create('advancement_ability', $advancementColumns);

        // 13 entries + Sniveling Coward (BJ) + Mini-Master (RJ).
        Schema::create('totem_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('flip_value')->nullable();
            $table->boolean('is_black_joker')->default(false);
            $table->boolean('is_red_joker')->default(false);
            $table->unsignedTinyInteger('df');
            $table->unsignedTinyInteger('wp');
            $table->unsignedTinyInteger('sp');
            $table->unsignedTinyInteger('health');
            // Each is a serialized array of card-shaped entries.
            $table->json('abilities')->nullable();
            $table->json('attack_actions')->nullable();
            $table->json('tactical_actions')->nullable();
            // Sniveling Coward: can be permanently replaced by any non-joker totem.
            $table->boolean('special_replace_with_other_totem')->default(false);
            // Mini-Master: picks one action from a master sharing the leader's keyword.
            $table->boolean('is_mini_master')->default(false);
            $table->timestamps();
        });

        // 7 entries. No flip — picked freely; max one per leader.
        Schema::create('summoning_advancement_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->json('stat_block')->nullable();
            $table->timestamps();
        });

        // 13 entries + 2 jokers.
        Schema::create('weekly_events_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->unsignedTinyInteger('flip_value')->nullable();
            $table->boolean('is_black_joker')->default(false);
            $table->boolean('is_red_joker')->default(false);
            // Some events introduce terrain markers placed at the start of the
            // game (Ancient Monument, Predatory Garden, Soulstone Vein, …).
            $table->json('terrain_marker_def')->nullable();
            $table->boolean('requires_placement')->default(false);
            // 'A Bullet with Your Name on It' reflips on second occurrence.
            $table->boolean('is_one_time')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_events_catalog');
        Schema::dropIfExists('summoning_advancement_catalog');
        Schema::dropIfExists('totem_catalog');
        Schema::dropIfExists('advancement_ability');
        Schema::dropIfExists('advancement_action');
        Schema::dropIfExists('advancement_tactical_mod');
        Schema::dropIfExists('advancement_attack_mod');
        Schema::dropIfExists('back_alley_doctor_results');
        Schema::dropIfExists('lucky_miss_catalog');
        Schema::dropIfExists('injury_catalog');
        Schema::dropIfExists('equipment_catalog');
        Schema::dropIfExists('crew_card_effects');
        Schema::dropIfExists('leader_archetypes');
    }
};
