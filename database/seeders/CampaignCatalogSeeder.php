<?php

namespace Database\Seeders;

use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use App\Enums\GameModeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\LuckyMiss;
use App\Models\Campaign\WeeklyEvent;
use App\Models\CustomCharacter;
use App\Models\Trigger;
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
 * admin UI under the consolidated core admin pages by a human with the book
 * in hand.
 *
 * Idempotent: skips any seed-group that already has rows.
 *
 * Post-consolidation: equipment / injuries live on the `upgrades` table,
 * crew card effects + ability advancements live on `abilities`, action +
 * summoning advancements live on `actions`, attack/tactical mods live on
 * `triggers`, totems live on `custom_characters`. All are filtered via
 * `game_mode_type = 'campaign'` + a discriminator column where needed.
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
        $exists = $this->campaignAbilityQuery()->where('is_crew_card_effect', true)->exists();
        if ($exists) {
            return;
        }

        foreach (['Expert Coordination', 'Heavy Blow', 'Shape the Landscape', 'Prepared For Anything', 'Loot Their Stash'] as $name) {
            Ability::factory()->create([
                'name' => $name,
                'description' => "Placeholder body for {$name}.",
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'is_crew_card_effect' => true,
                'requires_token_choice' => false,
                'requires_marker_choice' => false,
                'requires_upgrade_type_choice' => false,
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
        if (Trigger::query()->where('campaign_advancement_kind', 'attack')->exists()) {
            return;
        }

        foreach ([2, 5, 8, 11] as $fv) {
            Trigger::factory()->campaignAdvancementAttack()->create([
                'name' => "Attack Trigger {$fv}",
                'campaign_flip_value' => $fv,
                'campaign_modifier_type' => 'trigger',
                'suits' => 'crow',
            ]);
        }
        Trigger::factory()->campaignAdvancementAttack()->create([
            'name' => 'Sharpened Aim',
            'campaign_flip_value' => 7,
            'campaign_modifier_type' => 'skl',
            'campaign_skl_from' => 5,
            'campaign_skl_to' => 6,
        ]);
        Trigger::factory()->campaignAdvancementAttack()->create([
            'name' => "Master's Strike",
            'campaign_flip_value' => 12,
            'campaign_modifier_type' => 'signature',
            'campaign_grants_signature' => true,
        ]);
    }

    private function seedTacticalMods(): void
    {
        if (Trigger::query()->where('campaign_advancement_kind', 'tactical')->exists()) {
            return;
        }

        foreach ([2, 6, 9, 12] as $fv) {
            Trigger::factory()->campaignAdvancementTactical()->create([
                'name' => "Tactical Trigger {$fv}",
                'campaign_flip_value' => $fv,
                'campaign_modifier_type' => 'trigger',
                'suits' => 'tome',
            ]);
        }
        Trigger::factory()->campaignAdvancementTactical()->create([
            'name' => 'Refined Technique',
            'campaign_is_always_available' => true,
        ]);
    }

    private function seedAdvancementActions(): void
    {
        if (Action::query()->where('campaign_advancement_kind', 'action')->exists()) {
            return;
        }

        foreach ([3, 6, 9, 12] as $fv) {
            Action::factory()->create([
                'name' => "Learned Action {$fv}",
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'campaign_advancement_kind' => 'action',
                'campaign_flip_value' => $fv,
            ]);
        }
        Action::factory()->create([
            'name' => 'Push Off',
            'game_mode_type' => GameModeTypeEnum::Campaign->value,
            'campaign_advancement_kind' => 'action',
            'campaign_is_always_available' => true,
        ]);
    }

    private function seedAdvancementAbilities(): void
    {
        $exists = $this->campaignAbilityQuery()
            ->where('is_crew_card_effect', false)
            ->whereNotNull('campaign_flip_value')
            ->exists();
        if ($exists) {
            return;
        }

        foreach ([4, 7, 10, 13] as $fv) {
            Ability::factory()->create([
                'name' => "Gained Ability {$fv}",
                'game_mode_type' => GameModeTypeEnum::Campaign->value,
                'is_crew_card_effect' => false,
                'campaign_flip_value' => $fv,
            ]);
        }
        Ability::factory()->create([
            'name' => 'Stoic Resolve',
            'game_mode_type' => GameModeTypeEnum::Campaign->value,
            'is_crew_card_effect' => false,
            'campaign_is_always_available' => true,
        ]);
    }

    private function seedTotems(): void
    {
        if (CustomCharacter::query()->where('is_campaign_totem_template', true)->exists()) {
            return;
        }

        $systemUserId = $this->ensureSystemUser();

        foreach ([3, 7, 11] as $fv) {
            CustomCharacter::factory()->create([
                'user_id' => $systemUserId,
                'name' => "Spirit Familiar {$fv}",
                'display_name' => "Spirit Familiar {$fv}",
                'is_campaign_totem' => true,
                'is_campaign_totem_template' => true,
                'campaign_totem_flip_value' => $fv,
            ]);
        }

        CustomCharacter::factory()->create([
            'user_id' => $systemUserId,
            'name' => 'Sniveling Coward',
            'display_name' => 'Sniveling Coward',
            'is_campaign_totem' => true,
            'is_campaign_totem_template' => true,
            'campaign_is_black_joker_totem' => true,
        ]);
        CustomCharacter::factory()->create([
            'user_id' => $systemUserId,
            'name' => 'Mini Master',
            'display_name' => 'Mini Master',
            'is_campaign_totem' => true,
            'is_campaign_totem_template' => true,
            'campaign_is_mini_master' => true,
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
                'email' => $email,
                'password' => bcrypt(\Illuminate\Support\Str::random(40)),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }
}
