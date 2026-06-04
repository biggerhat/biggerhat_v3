<?php

use App\Enums\GameModeTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Campaign Catalog Consolidation — Phase 2: copy data from dedicated catalog
 * tables (equipment_catalog, injury_catalog, crew_card_effects, advancement_*,
 * summoning_advancement_catalog, totem_catalog) into the core catalog tables
 * (abilities / actions / triggers / upgrades / custom_characters) with
 * `game_mode_type = 'campaign'`, then rewire the campaign pivot tables to
 * point at the new core-table IDs.
 *
 * Three pivots get new FK columns alongside their existing legacy ones:
 *   - campaign_equipment.equipment_upgrade_id   (was equipment_catalog_id)
 *   - campaign_arsenal_model_injuries.injury_upgrade_id  (was injury_catalog_id)
 *   - campaign_leader_advancements.catalog_core_id  (was catalog_id, polymorphic)
 *
 * Phase 3 rewires consumer code to read/write the new columns. Phase 5 drops
 * the legacy columns + the dedicated tables.
 *
 * See `/home/maczirr/.claude/plans/floating-whistling-waterfall.md`.
 */
return new class extends Migration
{
    /** @var array<string, array<int, int>> [source-key => [old_id => new_id]] */
    private array $maps = [];

    public function up(): void
    {
        // Fresh installs that never had the dedicated tables — nothing to do.
        if (! Schema::hasTable('equipment_catalog')) {
            return;
        }

        $this->addPivotColumns();

        DB::transaction(function () {
            $this->maps['equipment'] = $this->migrateEquipment();
            $this->maps['injury'] = $this->migrateInjuries();
            $this->maps['crew_card'] = $this->migrateCrewCardEffects();
            $this->maps['advancement_ability'] = $this->migrateAdvancementAbilities();
            $this->maps['advancement_action'] = $this->migrateAdvancementActions();
            $this->maps['advancement_attack_mod'] = $this->migrateAdvancementMods('advancement_attack_mod', 'attack');
            $this->maps['advancement_tactical_mod'] = $this->migrateAdvancementMods('advancement_tactical_mod', 'tactical');
            $this->maps['summoning'] = $this->migrateSummoningAdvancements();
            $this->maps['totem'] = $this->migrateTotems();

            $this->rewirePivots();
        });
    }

    public function down(): void
    {
        // Best-effort: drop the new pivot columns. Restoring deleted catalog
        // data isn't attempted — restore from backup if needed.
        if (Schema::hasColumn('campaign_equipment', 'equipment_upgrade_id')) {
            Schema::table('campaign_equipment', function (Blueprint $table) {
                $table->dropColumn('equipment_upgrade_id');
            });
        }
        if (Schema::hasColumn('campaign_arsenal_model_injuries', 'injury_upgrade_id')) {
            Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
                $table->dropColumn('injury_upgrade_id');
            });
        }
        if (Schema::hasColumn('campaign_leader_advancements', 'catalog_core_id')) {
            Schema::table('campaign_leader_advancements', function (Blueprint $table) {
                $table->dropColumn('catalog_core_id');
            });
        }
    }

    // ───────────────────────────────────────────────────────────────
    // Pivot column additions
    // ───────────────────────────────────────────────────────────────

    private function addPivotColumns(): void
    {
        Schema::table('campaign_equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_equipment', 'equipment_upgrade_id')) {
                $table->unsignedBigInteger('equipment_upgrade_id')->nullable()->after('equipment_catalog_id');
                $table->index('equipment_upgrade_id', 'idx_ce_upgrade');
            }
            // Legacy FK is required-not-null on creation. Relax to nullable
            // so post-consolidation inserts can omit it; Phase 5 drops the
            // column entirely once nothing reads it.
            $table->unsignedBigInteger('equipment_catalog_id')->nullable()->change();
        });

        Schema::table('campaign_arsenal_model_injuries', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_arsenal_model_injuries', 'injury_upgrade_id')) {
                $table->unsignedBigInteger('injury_upgrade_id')->nullable()->after('injury_catalog_id');
                $table->index('injury_upgrade_id', 'idx_cami_upgrade');
            }
            $table->unsignedBigInteger('injury_catalog_id')->nullable()->change();
        });

        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_leader_advancements', 'catalog_core_id')) {
                $table->unsignedBigInteger('catalog_core_id')->nullable()->after('catalog_id');
                $table->index(['source_table', 'catalog_core_id'], 'idx_cla_source_core');
            }
            // catalog_id is already nullable in the original migration — no change.
        });
    }

    // ───────────────────────────────────────────────────────────────
    // Equipment + Injuries → upgrades
    // ───────────────────────────────────────────────────────────────

    /** @return array<int, int> */
    private function migrateEquipment(): array
    {
        $map = [];
        foreach (DB::table('equipment_catalog')->get() as $r) {
            $existingId = DB::table('upgrades')
                ->where('campaign_upgrade_kind', 'equipment')
                ->where('name', $r->name)
                ->where('campaign_br', $r->br)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('upgrades')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('upgrades', $r->name),
                'description' => $r->body ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_upgrade_kind' => 'equipment',
                'campaign_br' => $r->br,
                'campaign_cc' => $r->cc,
                'campaign_pool_suit_a' => $r->pool_suit_a ?? null,
                'campaign_pool_suit_b' => $r->pool_suit_b ?? null,
                'campaign_is_always_available' => (bool) ($r->is_always_available ?? false),
                'campaign_ttw_only' => (bool) ($r->ttw_only ?? false),
                'campaign_is_omens_mark' => (bool) ($r->is_omens_mark ?? false),
                'campaign_is_unique' => (bool) ($r->is_unique ?? false),
                'campaign_leader_only' => (bool) ($r->leader_only ?? false),
                'campaign_non_unique_only' => (bool) ($r->non_unique_only ?? false),
                'campaign_annihilate_after_game' => (bool) ($r->annihilate_after_game ?? false),
                'campaign_is_red_joker_entry' => (bool) ($r->is_red_joker_entry ?? false),
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function migrateInjuries(): array
    {
        $map = [];
        foreach (DB::table('injury_catalog')->get() as $r) {
            $existingId = DB::table('upgrades')
                ->where('campaign_upgrade_kind', 'injury')
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('upgrades')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('upgrades', $r->name),
                'description' => $r->body ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_upgrade_kind' => 'injury',
                'campaign_flip_value' => $r->flip_value,
                'campaign_suit_pool' => $r->suit_pool,
                'campaign_is_traitor' => (bool) ($r->is_traitor ?? false),
                'campaign_is_close_call' => (bool) ($r->is_close_call ?? false),
                'campaign_annihilates_model' => (bool) ($r->annihilates_model ?? false),
                'campaign_reflip_if_no_triggers' => (bool) ($r->reflip_if_no_triggers ?? false),
                'campaign_reflip_if_master_or_totem' => (bool) ($r->reflip_if_master_or_totem ?? false),
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    // ───────────────────────────────────────────────────────────────
    // Crew Card Effects + Advancement Ability → abilities
    // ───────────────────────────────────────────────────────────────

    /** @return array<int, int> */
    private function migrateCrewCardEffects(): array
    {
        $map = [];
        foreach (DB::table('crew_card_effects')->get() as $r) {
            $existingId = DB::table('abilities')
                ->where('is_crew_card_effect', true)
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('abilities')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('abilities', $r->name),
                'description' => $r->body ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'is_crew_card_effect' => true,
                'requires_token_choice' => (bool) ($r->requires_token_choice ?? false),
                'requires_marker_choice' => (bool) ($r->requires_marker_choice ?? false),
                'requires_upgrade_type_choice' => (bool) ($r->requires_upgrade_type_choice ?? false),
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function migrateAdvancementAbilities(): array
    {
        $map = [];
        foreach (DB::table('advancement_ability')->get() as $r) {
            $existingId = DB::table('abilities')
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->where('is_crew_card_effect', false)
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('abilities')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('abilities', $r->name),
                'description' => $r->body ?? null,
                'defensive_ability_type' => $r->defensive_ability_type ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_flip_value' => $r->flip_value,
                'campaign_is_always_available' => (bool) ($r->is_always_available ?? false),
                'campaign_joker_freechoice' => (bool) ($r->joker_freechoice ?? false),
                'is_crew_card_effect' => false,
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    // ───────────────────────────────────────────────────────────────
    // Advancement Action + Summoning → actions
    // ───────────────────────────────────────────────────────────────

    /** @return array<int, int> */
    private function migrateAdvancementActions(): array
    {
        $map = [];
        foreach (DB::table('advancement_action')->get() as $r) {
            $stat = $this->decode($r->stat_block ?? null);

            $existingId = DB::table('actions')
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->where('campaign_advancement_kind', 'action')
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('actions')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('actions', $r->name),
                'description' => $r->body ?? null,
                'range' => $stat['rg'] ?? null,
                'stat' => $stat['skl'] ?? null,
                'resisted_by' => $stat['rst'] ?? null,
                'target_number' => $stat['tn'] ?? null,
                'damage' => $stat['dmg'] ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_flip_value' => $r->flip_value,
                'campaign_is_always_available' => (bool) ($r->is_always_available ?? false),
                'campaign_joker_freechoice' => (bool) ($r->joker_freechoice ?? false),
                'campaign_grants_signature' => (bool) ($r->grants_signature ?? false),
                'campaign_advancement_kind' => 'action',
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function migrateSummoningAdvancements(): array
    {
        $map = [];
        foreach (DB::table('summoning_advancement_catalog')->get() as $r) {
            $stat = $this->decode($r->stat_block ?? null);

            $existingId = DB::table('actions')
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->where('campaign_advancement_kind', 'summoning')
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('actions')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('actions', $r->name),
                'description' => $r->body ?? null,
                'range' => $stat['rg'] ?? null,
                'stat' => $stat['skl'] ?? null,
                'resisted_by' => $stat['rst'] ?? null,
                'target_number' => $stat['tn'] ?? null,
                'damage' => $stat['dmg'] ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_advancement_kind' => 'summoning',
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    // ───────────────────────────────────────────────────────────────
    // Advancement Attack/Tactical Mods → triggers
    // ───────────────────────────────────────────────────────────────

    /** @return array<int, int> */
    private function migrateAdvancementMods(string $table, string $kind): array
    {
        $map = [];
        foreach (DB::table($table)->get() as $r) {
            $existingId = DB::table('triggers')
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->where('campaign_advancement_kind', $kind)
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('triggers')->insertGetId([
                'name' => $r->name,
                'slug' => $this->uniqueSlug('triggers', $r->name),
                'suits' => $r->suit ?? null,
                'description' => $r->body ?? null,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_flip_value' => $r->flip_value,
                'campaign_is_always_available' => (bool) ($r->is_always_available ?? false),
                'campaign_joker_freechoice' => (bool) ($r->joker_freechoice ?? false),
                'campaign_grants_signature' => (bool) ($r->grants_signature ?? false),
                'campaign_modifier_type' => $r->modifier_type ?? null,
                'campaign_skl_from' => $r->skl_from,
                'campaign_skl_to' => $r->skl_to,
                'campaign_advancement_kind' => $kind,
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    // ───────────────────────────────────────────────────────────────
    // Totem catalog → custom_characters (system-owned template rows)
    // ───────────────────────────────────────────────────────────────

    /** @return array<int, int> */
    private function migrateTotems(): array
    {
        $map = [];
        $rows = DB::table('totem_catalog')->get();
        if ($rows->isEmpty()) {
            return $map;
        }

        $systemUserId = $this->ensureSystemUser();

        foreach ($rows as $r) {
            $existingId = DB::table('custom_characters')
                ->where('is_campaign_totem_template', true)
                ->where('name', $r->name)
                ->value('id');
            if ($existingId) {
                $map[(int) $r->id] = (int) $existingId;

                continue;
            }

            $id = DB::table('custom_characters')->insertGetId([
                'user_id' => $systemUserId,
                'share_code' => Str::random(12),
                'name' => $r->name,
                'display_name' => $r->name,
                'slug' => $this->uniqueSlug('custom_characters', $r->name),
                'faction' => 'arcanists', // arbitrary — totem templates have no faction
                'health' => $r->health,
                'defense' => $r->df,
                'willpower' => $r->wp,
                'speed' => $r->sp,
                'is_campaign_totem' => true,
                'is_campaign_totem_template' => true,
                'campaign_totem_flip_value' => $r->flip_value,
                'campaign_is_black_joker_totem' => (bool) ($r->is_black_joker ?? false),
                'campaign_is_red_joker_totem' => (bool) ($r->is_red_joker ?? false),
                'campaign_totem_special_replace' => (bool) ($r->special_replace_with_other_totem ?? false),
                'campaign_is_mini_master' => (bool) ($r->is_mini_master ?? false),
                'actions' => $this->mergeActionsJson($r),
                'abilities' => $r->abilities,
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            $map[(int) $r->id] = (int) $id;
        }

        return $map;
    }

    // ───────────────────────────────────────────────────────────────
    // Pivot rewiring — point at new core IDs via the maps
    // ───────────────────────────────────────────────────────────────

    private function rewirePivots(): void
    {
        // campaign_equipment.equipment_catalog_id → upgrades.id (new column)
        $equipmentMap = $this->maps['equipment'] ?? [];
        foreach ($equipmentMap as $oldId => $newId) {
            DB::table('campaign_equipment')
                ->where('equipment_catalog_id', $oldId)
                ->update(['equipment_upgrade_id' => $newId]);
        }

        // campaign_arsenal_model_injuries.injury_catalog_id → upgrades.id (new column)
        $injuryMap = $this->maps['injury'] ?? [];
        foreach ($injuryMap as $oldId => $newId) {
            DB::table('campaign_arsenal_model_injuries')
                ->where('injury_catalog_id', $oldId)
                ->update(['injury_upgrade_id' => $newId]);
        }

        // campaign_leader_advancements.catalog_id → core tables (new column).
        // The source_table column says which dedicated table the old id pointed at.
        $advancementSourceMaps = [
            'attack_mod' => $this->maps['advancement_attack_mod'] ?? [],
            'tactical_mod' => $this->maps['advancement_tactical_mod'] ?? [],
            'action' => $this->maps['advancement_action'] ?? [],
            'ability' => $this->maps['advancement_ability'] ?? [],
            'totem' => $this->maps['totem'] ?? [],
            'summoning' => $this->maps['summoning'] ?? [],
            'crew_card' => $this->maps['crew_card'] ?? [],
        ];

        foreach ($advancementSourceMaps as $source => $map) {
            foreach ($map as $oldId => $newId) {
                DB::table('campaign_leader_advancements')
                    ->where('source_table', $source)
                    ->where('catalog_id', $oldId)
                    ->update(['catalog_core_id' => $newId]);
            }
        }

        // campaign_crews.crew_card_effect_id and
        // campaign_crew_card_advancements.crew_card_effect_id both point at
        // the (now removed) crew_card_effects table. After consolidation,
        // these columns reference abilities.id (with is_crew_card_effect=true).
        // Remap in-place so the existing column keeps working.
        $crewCardMap = $this->maps['crew_card'] ?? [];
        foreach ($crewCardMap as $oldId => $newId) {
            DB::table('campaign_crews')
                ->where('crew_card_effect_id', $oldId)
                ->update(['crew_card_effect_id' => $newId]);

            DB::table('campaign_crew_card_advancements')
                ->where('crew_card_effect_id', $oldId)
                ->update(['crew_card_effect_id' => $newId]);
        }
    }

    // ───────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────

    private function mergeActionsJson(object $r): ?string
    {
        $attack = $this->decode($r->attack_actions ?? null);
        $tactical = $this->decode($r->tactical_actions ?? null);

        $merged = [];
        foreach ($attack as $a) {
            $merged[] = is_array($a) ? array_merge($a, ['category' => 'attack']) : $a;
        }
        foreach ($tactical as $a) {
            $merged[] = is_array($a) ? array_merge($a, ['category' => 'tactical']) : $a;
        }

        return $merged === [] ? null : json_encode($merged);
    }

    /** @return array<int, mixed> */
    private function decode(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function ensureSystemUser(): int
    {
        $email = 'system-totem-templates@biggerhat.local';

        $id = DB::table('users')->where('email', $email)->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => 'System (Totem Templates)',
            'email' => $email,
            'password' => bcrypt(Str::random(40)),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uniqueSlug(string $table, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'campaign-'.Str::random(6);
        }

        $candidate = $base;
        $i = 0;
        while (DB::table($table)->where('slug', $candidate)->exists()) {
            $i++;
            $candidate = $base.'-c'.Str::random(4);
            if ($i > 50) {
                return $base.'-'.Str::random(8);
            }
        }

        return $candidate;
    }
};
