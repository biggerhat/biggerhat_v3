<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4E Campaign Mode — persistent state tables (Index of the Untold).
 *
 * Captures the cross-game lifecycle of a campaign: a campaign hosts players,
 * each player has a crew + leader (the leader lives on `custom_characters`
 * extended with campaign columns), the crew accumulates models / equipment /
 * injuries / advancements over weeks of play, and each game spawns an
 * aftermath wizard that mutates the arsenal.
 *
 * Catalog (rulebook reference data — archetypes, equipment catalog, injury
 * tables, etc.) lives in the sibling 2026_05_22_120100 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ───────────────────────────────────────────────────────────────
        // Campaign + membership
        // ───────────────────────────────────────────────────────────────

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Rulebook recommends 4–12 weeks; group can extend.
            $table->unsignedTinyInteger('length_weeks');
            $table->unsignedTinyInteger('current_week')->default(1);
            $table->foreignId('organizer_user_id')->constrained('users')->cascadeOnDelete();
            // planning → active → ended. Locks settings on transition to active.
            $table->string('status')->default('planning');
            // Optional rules toggled at creation: no_injuries, extra_scrip, stay_dead,
            // cut_em_up, corrupted_pawns, empowered_aftermath, evolving_leadership,
            // master_lead, weekly_events, bounties, black_market. Stored as a flat
            // JSON map so adding new toggles doesn't need a migration.
            $table->json('optional_rules')->nullable();
            $table->boolean('competitive')->default(false);
            $table->boolean('weekly_event_active')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });

        Schema::create('campaign_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->timestamp('starts_at')->nullable();
            // Rolled when weekly_events are active. FK target lives in catalog
            // migration; constrain after both migrations have run.
            $table->unsignedBigInteger('weekly_event_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'week_number']);
        });

        Schema::create('campaign_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('player'); // player | organizer
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id']);
        });

        Schema::create('campaign_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable(); // invited by email when no account exists
            $table->string('token')->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'accepted_at']);
        });

        // ───────────────────────────────────────────────────────────────
        // Crews (one per player per campaign) + their crew card
        // ───────────────────────────────────────────────────────────────

        Schema::create('campaign_crews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            // Public share token for the Arsenal Sheet (mirrors CustomCharacter::$share_code pattern).
            $table->string('share_code', 16)->unique();
            // FactionEnum value — nullable since crews are stubbed at invite-
            // accept time and the player declares faction during Leader Build.
            $table->string('faction')->nullable();
            $table->foreignId('keyword_1_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->foreignId('keyword_2_id')->nullable()->constrained('keywords')->nullOnDelete();
            // FK target lives in catalog migration; soft-link until then.
            $table->unsignedBigInteger('crew_card_effect_id')->nullable();
            $table->integer('scrip')->default(0);
            $table->unsignedInteger('total_wins')->default(0);
            // Once-per-leader miraculous-recovery flag is on the leader row
            // itself (custom_characters extension). Crew-level retire is here.
            $table->timestamp('retired_at')->nullable();
            $table->timestamp('starting_anew_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id']);
            $table->index('faction');
        });

        // Tier-4 crew-card borrows. Records which extra effects (from any
        // master sharing one of the crew's keywords) have been added.
        Schema::create('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_crew_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('crew_card_effect_id'); // FK after catalog migration
            $table->foreignId('source_master_id')->nullable()->constrained('characters')->nullOnDelete();
            // FK target lives in aftermath table created later in this migration.
            $table->unsignedBigInteger('acquired_aftermath_id')->nullable();
            $table->timestamps();
        });

        // ───────────────────────────────────────────────────────────────
        // Leader / Totem extension on custom_characters
        // ───────────────────────────────────────────────────────────────

        Schema::table('custom_characters', function (Blueprint $table) {
            // FK is nullable so existing non-campaign CustomCharacter rows are unaffected.
            $table->foreignId('campaign_crew_id')->nullable()->after('user_id')
                ->constrained('campaign_crews')->nullOnDelete();
            $table->boolean('is_campaign_leader')->default(false)->after('campaign_crew_id');
            $table->boolean('is_campaign_totem')->default(false)->after('is_campaign_leader');
            // LeaderArchetypeEnum value; null for non-campaign rows and totems.
            $table->string('archetype')->nullable()->after('is_campaign_totem');
            // LeaderTagEnum value; null for non-leader rows.
            $table->string('tag')->nullable()->after('archetype');
            // Campaign leaders use raw integer stats (not the signature/range
            // suit-stat strings used elsewhere on CustomCharacter).
            $table->unsignedTinyInteger('campaign_size')->nullable()->after('tag');
            $table->unsignedTinyInteger('campaign_health')->nullable()->after('campaign_size');
            $table->unsignedTinyInteger('campaign_df')->nullable()->after('campaign_health');
            $table->unsignedTinyInteger('campaign_wp')->nullable()->after('campaign_df');
            $table->unsignedTinyInteger('campaign_sp')->nullable()->after('campaign_wp');
            // Once-only Fate intervention on first annihilation (pg 20).
            $table->boolean('miraculous_recovery_used')->default(false)->after('campaign_sp');
            $table->timestamp('annihilated_at')->nullable()->after('miraculous_recovery_used');
            $table->timestamp('replaced_at')->nullable()->after('annihilated_at');
            // False on prior leaders within the same crew so history is preserved.
            $table->boolean('current')->default(true)->after('replaced_at');
            $table->index(['campaign_crew_id', 'is_campaign_leader', 'current'], 'idx_cc_crew_leader_current');
            $table->index(['campaign_crew_id', 'is_campaign_totem', 'current'], 'idx_cc_crew_totem_current');
        });

        // Origin record for a Tier-3 totem unlock (which catalog row spawned it).
        Schema::create('campaign_totem_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_character_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('totem_catalog_id'); // FK after catalog migration
            $table->string('base_size')->nullable(); // BaseSizeEnum value
            $table->timestamps();
        });

        // 27-box XP track (13 + 7 + 7). Stored as JSON for simplicity — UI
        // toggles individual boxes filled/unfilled, and the advancement
        // table records position_in_xp_track to track where a spend happened.
        Schema::create('campaign_leader_xp_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_character_id')->constrained()->cascadeOnDelete();
            // [{ index: 0, filled: bool }, ...] — 27 entries.
            $table->json('track');
            $table->timestamps();
        });

        Schema::create('campaign_leader_advancements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_character_id')->constrained()->cascadeOnDelete();
            // Source aftermath (nullable for the joker-replace flow on totem).
            $table->unsignedBigInteger('source_aftermath_id')->nullable();
            $table->string('source_table'); // AdvancementTableEnum value
            // Catalog id within the matching advancement_* table; null for the
            // "Choose freely" joker variant which stores its pick in free_choice.
            $table->unsignedBigInteger('catalog_id')->nullable();
            // Which entry in the leader's custom_characters.actions JSON array
            // the trigger / skl-boost / signature attaches to. -1 means N/A.
            $table->integer('applied_to_action_index')->default(-1);
            // For totem advancements where the user later reroutes to the totem.
            $table->foreignId('applied_to_custom_character_id')->nullable()
                ->constrained('custom_characters', 'id', 'fk_cla_applied_to_custom_char')
                ->nullOnDelete();
            $table->unsignedTinyInteger('position_in_xp_track');
            $table->json('free_choice')->nullable();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamps();
            $table->index(['custom_character_id', 'source_table'], 'idx_cla_character_source');
        });

        // ───────────────────────────────────────────────────────────────
        // Arsenal contents
        // ───────────────────────────────────────────────────────────────

        Schema::create('campaign_arsenal_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_crew_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('miniature_id')->nullable()->constrained('miniatures')->nullOnDelete();
            $table->string('label')->nullable(); // user-friendly disambig (e.g. "Mindless Zombie A")
            $table->boolean('is_peon')->default(false); // cached for fast aftermath queries
            // Titles share injuries (pg 18) — group key = master_name + base character id.
            $table->string('title_group_key')->nullable();
            $table->unsignedTinyInteger('acquired_week')->nullable();
            $table->string('acquired_via')->default('hire'); // hire | summon | joker | traitor | cut_em_up
            // Optional secondary keyword granted by the crew's pair (pg 15 "permanently gains the second keyword").
            $table->foreignId('granted_keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->timestamp('annihilated_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_crew_id', 'annihilated_at']);
            $table->index('title_group_key');
        });

        Schema::create('campaign_arsenal_model_injuries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_arsenal_model_id')
                ->constrained('campaign_arsenal_models', 'id', 'fk_camim_arsenal_model')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('injury_catalog_id'); // FK after catalog migration
            $table->unsignedBigInteger('acquired_aftermath_id')->nullable();
            $table->timestamps();
            $table->index('campaign_arsenal_model_id', 'idx_camim_arsenal_model');
        });

        Schema::create('campaign_arsenal_model_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_arsenal_model_id')
                ->constrained('campaign_arsenal_models', 'id', 'fk_camae_arsenal_model')
                ->cascadeOnDelete();
            // FK to the per-crew equipment instance (created below).
            $table->unsignedBigInteger('campaign_equipment_id');
            // Per-game attachment; null when stored unattached.
            $table->unsignedBigInteger('attached_for_game_id')->nullable();
            $table->timestamps();
            $table->index('campaign_arsenal_model_id', 'idx_camae_arsenal_model');
        });

        // ───────────────────────────────────────────────────────────────
        // Equipment (per-crew pool of upgrade-shaped items)
        // ───────────────────────────────────────────────────────────────

        Schema::create('campaign_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_crew_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_catalog_id'); // FK after catalog migration
            // Source of acquisition: barter | joker | cut_em_up | gift | starting_corrupted_pawns | starting_lucky_upstart
            $table->string('source')->default('barter');
            $table->unsignedBigInteger('acquired_aftermath_id')->nullable();
            $table->timestamp('annihilated_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_crew_id', 'annihilated_at']);
        });

        // ───────────────────────────────────────────────────────────────
        // Games + Aftermaths
        // ───────────────────────────────────────────────────────────────

        Schema::create('campaign_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->foreignId('crew_a_id')->constrained('campaign_crews')->cascadeOnDelete();
            $table->foreignId('crew_b_id')->constrained('campaign_crews')->cascadeOnDelete();
            // FK to the existing `games` table — the in-game tracker is reused.
            $table->foreignId('base_game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->unsignedSmallInteger('encounter_size');
            $table->integer('cr_a');
            $table->integer('cr_b');
            $table->unsignedTinyInteger('ss_bonus_to_lower')->default(0);
            $table->foreignId('winner_crew_id')->nullable()->constrained('campaign_crews')->nullOnDelete();
            $table->foreignId('withdrew_crew_id')->nullable()->constrained('campaign_crews')->nullOnDelete();
            $table->unsignedTinyInteger('withdrew_turn')->nullable();
            $table->unsignedTinyInteger('vp_a')->default(0);
            $table->unsignedTinyInteger('vp_b')->default(0);
            $table->unsignedTinyInteger('schemes_completed_a')->default(0);
            $table->unsignedTinyInteger('schemes_completed_b')->default(0);
            $table->unsignedBigInteger('weekly_event_id')->nullable(); // FK after catalog
            // setup | in_progress | aftermath | closed
            $table->string('status')->default('setup');
            $table->timestamps();
            $table->index(['campaign_id', 'week_number']);
        });

        Schema::create('campaign_aftermaths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_crew_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('current_phase')->default(1); // 1..6
            // Snapshot of the drawn hand: [{ value, suit, is_joker }, ...].
            // CRITICAL: the fate deck does NOT reshuffle until aftermath ends.
            // Persisting this means a refresh resumes mid-flow on the same hand.
            $table->json('hand_drawn')->nullable();
            // [{ card: {...}, used_for: 'barter|doctor|injury|advance', notes: '...' }]
            $table->json('hand_used')->nullable();
            $table->integer('scrip_earned')->default(0);
            // open | locked (finalize advances to locked; once locked no further edits).
            $table->string('status')->default('open');
            $table->timestamps();
            $table->unique(['campaign_game_id', 'campaign_crew_id']);
        });

        Schema::create('campaign_aftermath_barter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_aftermath_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('raw_flip_value');
            $table->string('raw_flip_suit')->nullable();
            $table->json('cheated_to')->nullable(); // { value, suit }
            $table->json('purchases')->nullable(); // [equipment_catalog_id, ...]
            $table->unsignedTinyInteger('red_joker_ttw_flip_value')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_aftermath_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_arsenal_model_id')->constrained('campaign_arsenal_models')->cascadeOnDelete();
            $table->foreignId('target_injury_id')->constrained('campaign_arsenal_model_injuries')->cascadeOnDelete();
            $table->unsignedTinyInteger('flip_value');
            $table->boolean('cheated')->default(false);
            // no_effect | removed | lucky_miss_reflip | oops_added_injury | gained_undead | gained_construct
            $table->string('outcome');
            $table->timestamps();
        });

        Schema::create('campaign_aftermath_injury', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_aftermath_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arsenal_model_id')->constrained('campaign_arsenal_models')->cascadeOnDelete();
            $table->unsignedTinyInteger('flip_value');
            $table->boolean('cheated')->default(false);
            $table->unsignedBigInteger('resulting_injury_id')->nullable(); // FK after catalog
            $table->boolean('resulted_in_annihilation')->default(false);
            $table->unsignedBigInteger('lucky_miss_catalog_id')->nullable(); // FK after catalog
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Reverse order to respect FKs. SQLite tolerates either; MySQL is strict.
        Schema::dropIfExists('campaign_aftermath_injury');
        Schema::dropIfExists('campaign_aftermath_doctor');
        Schema::dropIfExists('campaign_aftermath_barter');
        Schema::dropIfExists('campaign_aftermaths');
        Schema::dropIfExists('campaign_games');
        Schema::dropIfExists('campaign_equipment');
        Schema::dropIfExists('campaign_arsenal_model_equipment');
        Schema::dropIfExists('campaign_arsenal_model_injuries');
        Schema::dropIfExists('campaign_arsenal_models');
        Schema::dropIfExists('campaign_leader_advancements');
        Schema::dropIfExists('campaign_leader_xp_tracks');
        Schema::dropIfExists('campaign_totem_origins');
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropIndex('idx_cc_crew_leader_current');
            $table->dropIndex('idx_cc_crew_totem_current');
            $table->dropConstrainedForeignId('campaign_crew_id');
            $table->dropColumn([
                'is_campaign_leader', 'is_campaign_totem', 'archetype', 'tag',
                'campaign_size', 'campaign_health', 'campaign_df', 'campaign_wp', 'campaign_sp',
                'miraculous_recovery_used', 'annihilated_at', 'replaced_at', 'current',
            ]);
        });
        Schema::dropIfExists('campaign_crew_card_advancements');
        Schema::dropIfExists('campaign_crews');
        Schema::dropIfExists('campaign_invitations');
        Schema::dropIfExists('campaign_players');
        Schema::dropIfExists('campaign_weeks');
        Schema::dropIfExists('campaigns');
    }
};
