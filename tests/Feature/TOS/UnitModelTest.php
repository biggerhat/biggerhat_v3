<?php

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Enums\TOS\UnitSideEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Action;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\TOS\UnitSide;

it('factory creates a Unit with no sides by default', function () {
    $unit = Unit::factory()->create();

    expect($unit->sides()->count())->toBe(0);
});

it('withSides() state creates both standard and glory sides', function () {
    $unit = Unit::factory()->withSides()->create();

    $unit->refresh()->load('sides');

    expect($unit->sides()->count())->toBe(2)
        ->and($unit->standardSide())->not->toBeNull()
        ->and($unit->glorySide())->not->toBeNull()
        ->and($unit->standardSide()->side)->toBe(UnitSideEnum::Standard)
        ->and($unit->glorySide()->side)->toBe(UnitSideEnum::Glory);
});

it('commander state attaches the Commander Special Unit Rule', function () {
    $unit = Unit::factory()->withSides()->commander()->create();

    expect($unit->specialUnitRules->pluck('slug'))->toContain(SpecialUnitRuleEnum::Commander->value);
});

it('fireteam state attaches the Fireteam rule with parameters JSON', function () {
    $unit = Unit::factory()->withSides()->fireteam(baseMm: 30, modelsPerTeam: 3, modelSizeMm: 30)->create();

    $rule = $unit->specialUnitRules->firstWhere('slug', SpecialUnitRuleEnum::Fireteam->value);

    expect($rule)->not->toBeNull()
        ->and($rule->pivot->parameters)->toBe([
            'base_mm' => 30,
            'models_per_team' => 3,
            'model_size_mm' => 30,
        ]);
});

it('cascades delete to sides, sculpts, and pivots', function () {
    $unit = Unit::factory()->withSides()->fireteam()->create();
    UnitSculpt::factory()->forUnit($unit)->count(2)->create();
    $unit->allegiances()->attach(Allegiance::factory()->create()->id);

    $unitId = $unit->id;
    $sideIds = $unit->sides()->pluck('id')->toArray();
    $sculptIds = $unit->sculpts()->pluck('id')->toArray();

    $unit->delete();

    expect(Unit::find($unitId))->toBeNull()
        ->and(UnitSide::whereIn('id', $sideIds)->count())->toBe(0)
        ->and(UnitSculpt::whereIn('id', $sculptIds)->count())->toBe(0)
        ->and(\DB::table('tos_unit_special_rule')->where('unit_id', $unitId)->count())->toBe(0)
        ->and(\DB::table('tos_allegiance_unit')->where('unit_id', $unitId)->count())->toBe(0);
});

it('M:M Allegiance ↔ Unit attach/detach works', function () {
    $unit = Unit::factory()->create();
    $kingsEmpire = Allegiance::factory()->create(['name' => "King's Empire"]);
    $cult = Allegiance::factory()->malifaux()->create(['name' => 'Cult']);

    $unit->allegiances()->attach([$kingsEmpire->id, $cult->id]);

    expect($unit->fresh()->allegiances->pluck('id'))->toContain($kingsEmpire->id, $cult->id);

    $unit->allegiances()->detach($cult->id);

    expect($unit->fresh()->allegiances->pluck('id'))->toContain($kingsEmpire->id)
        ->not->toContain($cult->id);
});

it('belongsToMany Ability via the unit-side pivot', function () {
    $unit = Unit::factory()->withSides()->create();
    $ability = Ability::factory()->general()->create();

    $unit->standardSide()->abilities()->attach($ability->id);

    $reloaded = Unit::with(['sides.abilities'])->find($unit->id);
    expect($reloaded->standardSide()->abilities->pluck('id'))->toContain($ability->id)
        ->and($reloaded->glorySide()->abilities->count())->toBe(0);
});

it('belongsToMany Action via the unit-side pivot', function () {
    $unit = Unit::factory()->withSides()->create();
    $action = Action::factory()->melee()->create();

    $unit->glorySide()->actions()->attach($action->id);

    $reloaded = Unit::with(['sides.actions'])->find($unit->id);
    expect($reloaded->glorySide()->actions->pluck('id'))->toContain($action->id)
        ->and($reloaded->standardSide()->actions->count())->toBe(0);
});

it('effectiveGloryTactics falls back to tactics when glory_tactics is null', function () {
    $unit = Unit::factory()->create(['tactics' => '2', 'glory_tactics' => null]);

    expect($unit->effectiveGloryTactics())->toBe('2')
        ->and($unit->hasDistinctGloryTactics())->toBeFalse();
});

it('effectiveGloryTactics returns the override when glory_tactics is set', function () {
    $unit = Unit::factory()->create(['tactics' => '2', 'glory_tactics' => '4']);

    expect($unit->effectiveGloryTactics())->toBe('4')
        ->and($unit->hasDistinctGloryTactics())->toBeTrue();
});

it('hasDistinctGloryTactics is false when override matches the standard tactics', function () {
    $unit = Unit::factory()->create(['tactics' => '3', 'glory_tactics' => '3']);

    expect($unit->hasDistinctGloryTactics())->toBeFalse();
});
