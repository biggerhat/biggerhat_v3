<?php

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

it('builds every catalog model via factory without error', function () {
    expect(LeaderArchetype::factory()->create())->toBeInstanceOf(LeaderArchetype::class);
    expect(CrewCardEffect::factory()->create())->toBeInstanceOf(CrewCardEffect::class);
    expect(Equipment::factory()->create())->toBeInstanceOf(Equipment::class);
    expect(Injury::factory()->create())->toBeInstanceOf(Injury::class);
    expect(LuckyMiss::factory()->create())->toBeInstanceOf(LuckyMiss::class);
    expect(BackAlleyDoctorResult::factory()->create())->toBeInstanceOf(BackAlleyDoctorResult::class);
    expect(AdvancementAttackMod::factory()->create())->toBeInstanceOf(AdvancementAttackMod::class);
    expect(AdvancementTacticalMod::factory()->create())->toBeInstanceOf(AdvancementTacticalMod::class);
    expect(AdvancementAction::factory()->create())->toBeInstanceOf(AdvancementAction::class);
    expect(AdvancementAbility::factory()->create())->toBeInstanceOf(AdvancementAbility::class);
    expect(Totem::factory()->create())->toBeInstanceOf(Totem::class);
    expect(SummoningAdvancement::factory()->create())->toBeInstanceOf(SummoningAdvancement::class);
    expect(WeeklyEvent::factory()->create())->toBeInstanceOf(WeeklyEvent::class);
});

it('LeaderArchetype heavyHitter state matches rulebook stats', function () {
    $hh = LeaderArchetype::factory()->heavyHitter()->create();

    expect($hh->df)->toBe(6);
    expect($hh->wp)->toBe(4);
    expect($hh->sp)->toBe(6);
    expect($hh->health)->toBe(14);
    expect($hh->attack_gets_trigger)->toBeTrue();
    expect($hh->attack_action_cost_cap)->toBe(10);
    expect($hh->tactical_action_cost_cap)->toBe(5);
    expect($hh->abilities_count)->toBe(0);
});

it('Equipment::scopeBarterableAt returns always-available + below-or-equal-flip rows', function () {
    Equipment::factory()->alwaysAvailable()->create(['name' => 'Pistol']);
    Equipment::factory()->create(['name' => 'Helmet', 'br' => 1]);
    Equipment::factory()->create(['name' => 'Coffee', 'br' => 4]);
    Equipment::factory()->create(['name' => 'Whiskey', 'br' => 6]);
    Equipment::factory()->thoseWhoThirst()->create(['name' => 'Edict', 'br' => 5]);

    $names = Equipment::query()->barterableAt(5)->pluck('name')->all();

    expect($names)->toContain('Pistol', 'Helmet', 'Coffee')
        ->not->toContain('Whiskey', 'Edict');
});

it('Injury traitor state flags Black Joker semantics', function () {
    $traitor = Injury::factory()->traitor()->create();
    expect($traitor->is_traitor)->toBeTrue();
    expect($traitor->reflip_if_master_or_totem)->toBeTrue();
    expect($traitor->suit_pool)->toBe('black_joker');
});

it('Injury killedOff annihilates the model', function () {
    $killed = Injury::factory()->killedOff()->create();
    expect($killed->annihilates_model)->toBeTrue();
    expect($killed->flip_value)->toBe(13);
});

it('casts equipment JSON columns as arrays', function () {
    $e = Equipment::factory()->create([
        'granted_ability' => ['name' => 'Armor', 'body' => 'reduce damage'],
    ]);
    $e->refresh();
    expect($e->granted_ability)->toBeArray()->toHaveKey('name', 'Armor');
});
