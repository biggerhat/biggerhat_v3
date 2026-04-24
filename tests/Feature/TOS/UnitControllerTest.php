<?php

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;

it('renders the units index with all units', function () {
    Unit::factory()->withSides()->commander()->count(2)->create();
    Unit::factory()->withSides()->titan()->create();

    $this->get(route('tos.units.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Units/Index')->has('units', 3));
});

it('filters the units index by special-rule slug via friendly URL', function () {
    Unit::factory()->withSides()->commander()->count(2)->create();
    Unit::factory()->withSides()->titan()->create();

    $this->get(route('tos.units.commander'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('rule_filter', SpecialUnitRuleEnum::Commander->value)->has('units', 2));
});

it('filters via the titan friendly URL', function () {
    Unit::factory()->withSides()->commander()->create();
    Unit::factory()->withSides()->titan()->create();

    $this->get(route('tos.units.titan'))
        ->assertInertia(fn ($p) => $p->where('rule_filter', SpecialUnitRuleEnum::Titan->value)->has('units', 1));
});

it('view resolves a unit by sculpt slug', function () {
    $unit = Unit::factory()->withSides()->commander()->create(['name' => 'Earl Burns']);
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create(['slug' => 'earl-burns-sculpt']);
    $unit->allegiances()->attach(Allegiance::factory()->create()->id);

    $this->get(route('tos.units.view', $sculpt->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Units/View')
            ->where('unit.name', 'Earl Burns')
            ->where('active_sculpt.slug', 'earl-burns-sculpt')
            ->has('unit.sides', 2)
        );
});

it('returns 404 for an unknown sculpt slug', function () {
    $this->get(route('tos.units.view', 'does-not-exist'))->assertNotFound();
});
