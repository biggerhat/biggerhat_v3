<?php

namespace Database\Seeders;

use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use App\Enums\GameModeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\LuckyMiss;
use App\Models\Campaign\WeeklyEvent;
use App\Models\CustomCharacter;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dev/CI convenience seeder for Campaign Mode (Index of the Untold) catalogs.
 *
 * NOT the rulebook — placeholder content so the Leader Builder, Starting
 * Arsenal, Weekly Hire, and Aftermath wizard are end-to-end clickable in
 * local dev and feature tests. Real catalog data is entered through the
 * admin UI by a human with the book in hand.
 *
 * Idempotent: skips any seed-group that already has rows.
 *
 * Post-consolidation: equipment / injuries live on the `upgrades` table,
 * crew card effects live on `abilities`, summoning advancements live on
 * `actions`, totems live on `custom_characters` — all filtered via
 * `game_mode_type = 'campaign'` + a discriminator column where needed.
 * Attack Mod / Tactical Mod / Action / Ability advancements have their own
 * dedicated tables (advancement_attack_mods / advancement_tactical_mods /
 * advancement_actions / advancement_abilities).
 */
class CampaignCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Archetypes live entirely on LeaderArchetypeEnum — no seeding needed.
        $this->seedCrewCardEffects();
        $this->seedEquipment();
        $this->seedInjuries();
        $this->seedLuckyMiss();
        $this->seedBackAlleyDoctor();
        $this->seedAttackMods();
        $this->seedTacticalMods();
        $this->seedAdvancementActions();
        $this->seedAdvancementAbilities();
        $this->seedTotems();
        $this->seedSummoningAdvancements();
        $this->seedWeeklyEvents();
    }

    private function campaignAbilityQuery()
    {
        return Ability::query()->where('game_mode_type', GameModeTypeEnum::Campaign->value);
    }

    private function seedCrewCardEffects(): void
    {
        if (CampaignCrewCard::query()->exists()) {
            return;
        }

        foreach (['Expert Coordination', 'Heavy Blow', 'Shape the Landscape', 'Prepared For Anything', 'Loot Their Stash'] as $name) {
            CampaignCrewCard::factory()->create([
                'name' => $name,
                'description' => "Placeholder body for {$name}.",
            ]);
        }
    }

    private function seedEquipment(): void
    {
        if (Upgrade::query()->where('campaign_upgrade_kind', 'equipment')->exists()) {
            return;
        }

        foreach ([1, 3, 5, 7, 9, 11, 13] as $br) {
            Upgrade::factory()->campaignEquipment()->create([
                'name' => "Placeholder Equipment BR{$br}",
                'campaign_br' => $br,
                'campaign_cc' => max(1, (int) floor($br / 4)),
            ]);
        }

        Upgrade::factory()->campaignEquipmentAlwaysAvailable()->create(['name' => 'Battered Trinket']);
        Upgrade::factory()->campaignEquipmentAlwaysAvailable()->create(['name' => 'Spare Soulstone']);
        Upgrade::factory()->campaignEquipmentTtw()->create(['name' => 'Whispering Shard', 'campaign_br' => 7]);
    }

    private function seedInjuries(): void
    {
        if (Upgrade::query()->where('campaign_upgrade_kind', 'injury')->exists()) {
            return;
        }

        foreach ([1, 4, 7, 10] as $fv) {
            Upgrade::factory()->campaignInjury()->create([
                'name' => "Lingering Wound {$fv}",
                'campaign_flip_value' => $fv,
                'campaign_suit_pool' => 'pc',
            ]);
        }

        Upgrade::factory()->campaignInjuryKilledOff()->create();
        Upgrade::factory()->campaignInjury()->create([
            'name' => 'Traitor',
            'campaign_is_traitor' => true,
        ]);
    }

    private function seedLuckyMiss(): void
    {
        if (LuckyMiss::query()->exists()) {
            return;
        }

        foreach ([3, 8, 13] as $fv) {
            LuckyMiss::factory()->create([
                'name' => "Lucky Miss {$fv}",
                'flip_value' => $fv,
            ]);
        }
    }

    private function seedBackAlleyDoctor(): void
    {
        if (BackAlleyDoctorResult::query()->exists()) {
            return;
        }

        // Full 1–13 coverage is mandatory — the Phase 5 doctor flip lookup is
        // range-based and falls through to null on missing rows.
        BackAlleyDoctorResult::factory()->create([
            'name' => 'Black Joker — Disaster',
            'body' => 'No effect. Scrip is spent anyway.',
            'flip_value_min' => null, 'flip_value_max' => null,
            'is_black_joker' => true,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::NoEffect->value,
        ]);

        BackAlleyDoctorResult::factory()->create([
            'name' => 'Oops',
            'body' => 'No removal; a new injury is inflicted in its place.',
            'flip_value_min' => 1, 'flip_value_max' => 8,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::AddedInjury->value,
        ]);

        BackAlleyDoctorResult::factory()->create([
            'name' => 'How many fingers do you need?',
            'body' => 'Annihilate the chosen injury, then reflip on the injury chart for a new one.',
            'flip_value_min' => 9, 'flip_value_max' => 9,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::RemovedAndReflip->value,
        ]);

        BackAlleyDoctorResult::factory()->create([
            'name' => 'Necromantic Improvement',
            'body' => 'Injury removed; model gains Undead.',
            'flip_value_min' => 10, 'flip_value_max' => 10,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::GainedUndead->value,
        ]);

        BackAlleyDoctorResult::factory()->create([
            'name' => 'Mechanical Augment',
            'body' => 'Injury removed; model gains Construct.',
            'flip_value_min' => 11, 'flip_value_max' => 11,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::GainedConstruct->value,
        ]);

        BackAlleyDoctorResult::factory()->create([
            'name' => 'Clean Recovery',
            'body' => 'Injury removed with no complications.',
            'flip_value_min' => 12, 'flip_value_max' => 13,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::Removed->value,
        ]);

        BackAlleyDoctorResult::factory()->create([
            'name' => 'Red Joker — Lucky Miss',
            'body' => 'Annihilate, then reflip on the Lucky Miss table.',
            'flip_value_min' => null, 'flip_value_max' => null,
            'is_red_joker' => true,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::LuckyMissReflip->value,
        ]);
    }

    private function seedAttackMods(): void
    {
        if (AdvancementAttackMod::query()->exists()) {
            return;
        }

        foreach ([2, 5, 8, 11] as $fv) {
            AdvancementAttackMod::factory()->create([
                'name' => "Attack Trigger {$fv}",
                'flip_value' => $fv,
                'modifier_type' => 'trigger',
                'suit' => 'crow',
            ]);
        }
        AdvancementAttackMod::factory()->sklBoost(5, 6)->create([
            'name' => 'Sharpened Aim',
            'flip_value' => 7,
        ]);
        AdvancementAttackMod::factory()->signature()->create([
            'name' => "Master's Strike",
            'flip_value' => 12,
        ]);

        // Any Joker rows (pg 40) — either color qualifies.
        AdvancementAttackMod::factory()->anyJoker()->create([
            'name' => 'Cruel Lessons',
            'effect_text' => 'Enemy leader only. After killing, this model gains +1 experience point during this game\'s aftermath phase.',
        ]);
        AdvancementAttackMod::factory()->anyJoker()->create([
            'name' => 'Consult the Bones',
            'effect_text' => 'Once per turn. Draw two cards, then discard a card.',
        ]);
    }

    private function seedTacticalMods(): void
    {
        if (AdvancementTacticalMod::query()->exists()) {
            return;
        }

        foreach ([2, 6, 9, 12] as $fv) {
            AdvancementTacticalMod::factory()->create([
                'name' => "Tactical Trigger {$fv}",
                'flip_value' => $fv,
                'modifier_type' => 'trigger',
                'suit' => 'tome',
            ]);
        }
        AdvancementTacticalMod::factory()->alwaysAvailable()->create([
            'name' => 'Refined Technique',
        ]);

        // Skl Boost rows with a qualifying range (pg 40-43) — Tactical Mod's read
        // "Skl of X or Y", unlike Attack Mod's single-value equivalents.
        AdvancementTacticalMod::factory()->sklBoost(0, 2, 1)->create([
            'name' => 'Tactical Skill Boost 7',
            'flip_value' => 7,
        ]);
        AdvancementTacticalMod::factory()->sklBoost(2, 4, 3)->create([
            'name' => 'Tactical Skill Boost 12',
            'flip_value' => 12,
        ]);

        // Color-specific Joker rows (pg 43) — Red and Black grant different triggers.
        AdvancementTacticalMod::factory()->redJoker()->create([
            'name' => 'Illumination of Illios',
            'effect_text' => 'Your opponent must tell you one of the schemes from their available pool they did not select.',
        ]);
        AdvancementTacticalMod::factory()->blackJoker()->create([
            'name' => 'Darkness of Delios',
            'effect_text' => 'You may abandon your scheme without showing it to your opponent and immediately choose a new scheme from its next available schemes.',
        ]);
    }

    private function seedAdvancementActions(): void
    {
        if (AdvancementAction::query()->exists()) {
            return;
        }

        foreach ([3, 6, 9, 12] as $fv) {
            AdvancementAction::factory()->create([
                'talent_name' => "Learned Action {$fv}",
                'flip_value' => $fv,
            ]);
        }
        AdvancementAction::factory()->alwaysAvailable()->create([
            'talent_name' => 'Push Off',
        ]);
    }

    private function seedAdvancementAbilities(): void
    {
        if (AdvancementAbility::query()->exists()) {
            return;
        }

        foreach ([4, 7, 10, 13] as $fv) {
            AdvancementAbility::factory()->create([
                'talent_name' => "Gained Ability {$fv}",
                'flip_value' => $fv,
            ]);
        }
        AdvancementAbility::factory()->alwaysAvailable()->create([
            'talent_name' => 'Stoic Resolve',
        ]);
    }

    private function seedTotems(): void
    {
        if (CustomCharacter::query()->where('is_campaign_totem_template', true)->exists()) {
            return;
        }

        $systemUserId = $this->ensureSystemUser();

        // Modest placeholder stats — these template rows just need valid
        // non-null stat columns; share_code/slug are auto-generated on create.
        $totemStats = ['health' => 3, 'defense' => 4, 'willpower' => 5, 'speed' => 4];

        foreach ([3, 7, 11] as $fv) {
            CustomCharacter::create([
                'user_id' => $systemUserId,
                'name' => "Spirit Familiar {$fv}",
                'display_name' => "Spirit Familiar {$fv}",
                'is_campaign_totem' => true,
                'is_campaign_totem_template' => true,
                'campaign_totem_flip_value' => $fv,
                ...$totemStats,
            ]);
        }

        CustomCharacter::create([
            'user_id' => $systemUserId,
            'name' => 'Sniveling Coward',
            'display_name' => 'Sniveling Coward',
            'is_campaign_totem' => true,
            'is_campaign_totem_template' => true,
            'campaign_is_black_joker_totem' => true,
            ...$totemStats,
        ]);
        CustomCharacter::create([
            'user_id' => $systemUserId,
            'name' => 'Mini Master',
            'display_name' => 'Mini Master',
            'is_campaign_totem' => true,
            'is_campaign_totem_template' => true,
            'campaign_is_mini_master' => true,
            ...$totemStats,
        ]);
    }

    private function seedSummoningAdvancements(): void
    {
        if (Action::query()->where('campaign_advancement_kind', 'summoning')->exists()) {
            return;
        }

        foreach (['Lesser Familiar', 'Bound Specter', 'Animated Effigy', 'Conjured Servant'] as $name) {
            Action::factory()->create([
                'name' => $name,
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_advancement_kind' => 'summoning',
            ]);
        }
    }

    private function seedWeeklyEvents(): void
    {
        if (WeeklyEvent::query()->exists()) {
            return;
        }

        foreach ([2, 6, 9, 13] as $fv) {
            WeeklyEvent::factory()->create([
                'name' => "Strange Week {$fv}",
                'flip_value' => $fv,
            ]);
        }

        WeeklyEvent::factory()->create([
            'name' => 'Black Joker Disaster',
            'flip_value' => null,
            'is_black_joker' => true,
        ]);
    }

    private function ensureSystemUser(): int
    {
        $email = 'system-totem-templates@biggerhat.local';

        return (int) (User::query()->where('email', $email)->value('id')
            ?? DB::table('users')->insertGetId([
                'name' => 'System (Totem Templates)',
                'slug' => 'system-totem-templates',
                'email' => $email,
                'password' => bcrypt(\Illuminate\Support\Str::random(40)),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }
}
