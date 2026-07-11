<?php

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;

it('renders the units index with all units', function () {
    Unit::factory()->withSides()->commander()->count(2)->create();
    Unit::factory()->withSides()->titan()->create();

    $this->get(route('tos.units.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Units/Index')->has('units.data', 3));
});

it('filters the units index by special-rule slug via friendly URL', function () {
    Unit::factory()->withSides()->commander()->count(2)->create();
    Unit::factory()->withSides()->titan()->create();

    $this->get(route('tos.units.commander'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('rule_filter', SpecialUnitRuleEnum::Commander->value)->has('units.data', 2));
});

it('filters via the titan friendly URL', function () {
    Unit::factory()->withSides()->commander()->create();
    Unit::factory()->withSides()->titan()->create();

    $this->get(route('tos.units.titan'))
        ->assertInertia(fn ($p) => $p->where('rule_filter', SpecialUnitRuleEnum::Titan->value)->has('units.data', 1));
});

it('excludes Commanders from the champions filter', function () {
    $champRule = SpecialUnitRule::factory()->create(['slug' => 'champion', 'name' => 'Champion']);
    $cmdrRule = SpecialUnitRule::factory()->create(['slug' => 'commander', 'name' => 'Commander']);

    $champ = Unit::factory()->withSides()->create(['name' => 'Pure Champion']);
    $champ->specialUnitRules()->sync([$champRule->id]);

    // A Commander is also a Champion, but has its own listing.
    $cmdr = Unit::factory()->withSides()->create(['name' => 'Cmdr Champion']);
    $cmdr->specialUnitRules()->sync([$cmdrRule->id, $champRule->id]);

    $this->get(route('tos.units.champion'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('units.data', 1)->where('units.data.0.name', 'Pure Champion'));
});

it('filters the units index by allegiance', function () {
    $union = Allegiance::factory()->create(['slug' => 'union', 'name' => 'Union']);
    $confederacy = Allegiance::factory()->create(['slug' => 'confederacy', 'name' => 'Immortal Confederacy']);

    $unionUnit = Unit::factory()->withSides()->create(['name' => 'Union Trooper']);
    $unionUnit->allegiances()->attach($union->id);

    $confederacyUnit = Unit::factory()->withSides()->create(['name' => 'Confederacy Trooper']);
    $confederacyUnit->allegiances()->attach($confederacy->id);

    $this->get(route('tos.units.index', ['allegiance' => 'union']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('allegiance_filter', 'union')
            ->has('units.data', 1)
            ->where('units.data.0.name', 'Union Trooper'));
});

it('exposes the allegiance select options on the units index', function () {
    Allegiance::factory()->create(['slug' => 'union', 'name' => 'Union']);

    $this->get(route('tos.units.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('allegiances', 1)->where('allegiances.0.value', 'union'));
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
