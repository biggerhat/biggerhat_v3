<?php

namespace Database\Seeders;

use App\Enums\BackAlleyDoctorOutcomeEnum;
use App\Enums\LeaderArchetypeEnum;
use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CrewCardEffect;
use App\Models\Campaign\Equipment;
use App\Models\Campaign\Injury;
use App\Models\Campaign\LeaderArchetype;
use App\Models\Campaign\LuckyMiss;
use App\Models\Campaign\SummoningAdvancement;
use App\Models\Campaign\Totem;
use App\Models\Campaign\WeeklyEvent;
use Illuminate\Database\Seeder;

/**
 * Dev/CI convenience seeder for Campaign Mode (Index of the Untold) catalogs.
 *
 * NOT the rulebook — placeholder content so the Leader Builder, Starting
 * Arsenal, Weekly Hire, and Aftermath wizard are end-to-end clickable in
 * local dev and feature tests. Real catalog data is entered through the
 * admin UI under /admin/campaign/* by a human with the book in hand.
 *
 * Idempotent: skips any table that already has rows.
 */
class CampaignCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedArchetypes();
        $this->seedCrewCardEffects();
        $this->seedEquipment();
        $this->seedInjuries();
        $this->seedLuckyMiss();
        $this->seedBackAlleyDoctor();
        $this->seedAdvancements();
        $this->seedTotems();
        $this->seedSummoningAdvancements();
        $this->seedWeeklyEvents();
    }

    private function seedArchetypes(): void
    {
        if (LeaderArchetype::query()->exists()) {
            return;
        }

        // All 5 archetypes — Leader Builder dropdown is non-functional without them.
        LeaderArchetype::factory()->create([
            'slug' => LeaderArchetypeEnum::LuckyUpstart->value,
            'name' => 'Lucky Upstart',
            'df' => 5, 'wp' => 5, 'sp' => 6, 'health' => 12,
            'attack_actions_count' => 1, 'attack_action_cost_cap' => 6, 'attack_gets_trigger' => false,
            'tactical_actions_count' => 1, 'tactical_action_cost_cap' => 6,
            'abilities_count' => 1, 'ability_cost_cap' => 6,
            'special_notes' => 'Starts with a free equipment item rolled on creation.',
        ]);

        LeaderArchetype::factory()->create();

        LeaderArchetype::factory()->heavyHitter()->create();

        LeaderArchetype::factory()->create([
            'slug' => LeaderArchetypeEnum::Schemer->value,
            'name' => 'Schemer',
            'df' => 5, 'wp' => 6, 'sp' => 7, 'health' => 12,
            'attack_actions_count' => 1, 'attack_action_cost_cap' => 6,
            'tactical_actions_count' => 2, 'tactical_action_cost_cap' => 8,
            'abilities_count' => 1, 'ability_cost_cap' => 7,
        ]);

        LeaderArchetype::factory()->create([
            'slug' => LeaderArchetypeEnum::TalentedIndividual->value,
            'name' => 'Talented Individual',
            'df' => 5, 'wp' => 5, 'sp' => 6, 'health' => 13,
            'attack_actions_count' => 1, 'attack_action_cost_cap' => 8,
            'tactical_actions_count' => 1, 'tactical_action_cost_cap' => 8,
            'abilities_count' => 2, 'ability_cost_cap' => 7,
        ]);
    }

    private function seedCrewCardEffects(): void
    {
        if (CrewCardEffect::query()->exists()) {
            return;
        }

        foreach (['Expert Coordination', 'Heavy Blow', 'Shape the Landscape', 'Prepared For Anything', 'Loot Their Stash'] as $name) {
            CrewCardEffect::factory()->create(['name' => $name]);
        }
    }

    private function seedEquipment(): void
    {
        if (Equipment::query()->exists()) {
            return;
        }

        // Mix of BR ranges so the Barter flip surfaces real options at every band.
        foreach ([1, 3, 5, 7, 9, 11, 13] as $br) {
            Equipment::factory()->create([
                'name' => "Placeholder Equipment BR{$br}",
                'br' => $br,
                'cc' => max(1, (int) floor($br / 4)),
            ]);
        }

        Equipment::factory()->alwaysAvailable()->create(['name' => 'Battered Trinket']);
        Equipment::factory()->alwaysAvailable()->create(['name' => 'Spare Soulstone']);
        Equipment::factory()->thoseWhoThirst()->create(['name' => 'Whispering Shard', 'br' => 7]);
    }

    private function seedInjuries(): void
    {
        if (Injury::query()->exists()) {
            return;
        }

        foreach ([1, 4, 7, 10] as $fv) {
            Injury::factory()->create([
                'name' => "Lingering Wound {$fv}",
                'flip_value' => $fv,
                'suit_pool' => 'pc',
            ]);
        }

        Injury::factory()->killedOff()->create();
        Injury::factory()->traitor()->create();
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
            'name' => 'Patched Up',
            'body' => 'Targeted injury is removed.',
            'flip_value_min' => 9, 'flip_value_max' => 9,
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::Removed->value,
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

    private function seedAdvancements(): void
    {
        // Each of the four flip-based advancement tables: mix of trigger /
        // skl-boost / signature / always-available so the Phase 4 picker has
        // something at every band.
        if (! AdvancementAttackMod::query()->exists()) {
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
        }

        if (! AdvancementTacticalMod::query()->exists()) {
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
        }

        if (! AdvancementAction::query()->exists()) {
            foreach ([3, 6, 9, 12] as $fv) {
                AdvancementAction::factory()->create([
                    'name' => "Learned Action {$fv}",
                    'flip_value' => $fv,
                ]);
            }
            AdvancementAction::factory()->alwaysAvailable()->create([
                'name' => 'Push Off',
            ]);
        }

        if (! AdvancementAbility::query()->exists()) {
            foreach ([4, 7, 10, 13] as $fv) {
                AdvancementAbility::factory()->create([
                    'name' => "Gained Ability {$fv}",
                    'flip_value' => $fv,
                ]);
            }
            AdvancementAbility::factory()->alwaysAvailable()->create([
                'name' => 'Stoic Resolve',
            ]);
        }
    }

    private function seedTotems(): void
    {
        if (Totem::query()->exists()) {
            return;
        }

        foreach ([3, 7, 11] as $fv) {
            Totem::factory()->create([
                'name' => "Spirit Familiar {$fv}",
                'flip_value' => $fv,
            ]);
        }

        Totem::factory()->snivelingCoward()->create();
        Totem::factory()->miniMaster()->create();
    }

    private function seedSummoningAdvancements(): void
    {
        if (SummoningAdvancement::query()->exists()) {
            return;
        }

        foreach (['Lesser Familiar', 'Bound Specter', 'Animated Effigy', 'Conjured Servant'] as $name) {
            SummoningAdvancement::factory()->create(['name' => $name]);
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
}
