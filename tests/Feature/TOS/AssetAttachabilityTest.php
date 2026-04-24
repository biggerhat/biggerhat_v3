<?php

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Unit;

it('Restricted-by-Unit-Name matches exact unit and rejects others', function () {
    $earl = Unit::factory()->withSides()->commander()->create(['slug' => 'earl-burns', 'name' => 'Earl Burns']);
    $other = Unit::factory()->withSides()->titan()->create();

    $asset = Asset::factory()->restrictedByUnit($earl)->create();

    expect($asset->canAttachTo($earl))->toBeTrue()
        ->and($asset->canAttachTo($other))->toBeFalse();
});

it('Restricted-by-Unit-Type matches units carrying the rule slug', function () {
    $commander = Unit::factory()->withSides()->commander()->create();
    $titan = Unit::factory()->withSides()->titan()->create();

    $asset = Asset::factory()->restrictedByUnitType(SpecialUnitRuleEnum::Commander->value)->create();

    expect($asset->canAttachTo($commander))->toBeTrue()
        ->and($asset->canAttachTo($titan))->toBeFalse();
});

it('Restricted-by-Allegiance matches units in that allegiance', function () {
    $ke = Allegiance::factory()->create(['slug' => 'kings-empire', 'name' => "King's Empire"]);
    $cult = Allegiance::factory()->malifaux()->create();

    $keUnit = Unit::factory()->withSides()->commander()->create();
    $keUnit->allegiances()->attach($ke->id);

    $cultUnit = Unit::factory()->withSides()->create();
    $cultUnit->allegiances()->attach($cult->id);

    $asset = Asset::factory()->restrictedByAllegiance($ke)->create();

    expect($asset->canAttachTo($keUnit))->toBeTrue()
        ->and($asset->canAttachTo($cultUnit))->toBeFalse();
});

it('Adjunct matches units whose Fireteam base_mm equals the size param', function () {
    $thirtyMm = Unit::factory()->withSides()->fireteam(baseMm: 30, modelsPerTeam: 3, modelSizeMm: 30)->create();
    $fortyMm = Unit::factory()->withSides()->fireteam(baseMm: 40, modelsPerTeam: 1, modelSizeMm: 40)->create();

    $asset = Asset::factory()->adjunct(30)->create();

    expect($asset->canAttachTo($thirtyMm))->toBeTrue()
        ->and($asset->canAttachTo($fortyMm))->toBeFalse();
});

it('Slot and Unique limits are skipped in canAttachTo (Company-scope rules)', function () {
    $unit = Unit::factory()->withSides()->create();
    $asset = Asset::factory()->slot('Head')->unique()->create();

    expect($asset->canAttachTo($unit))->toBeTrue();
});

it('Combined Restricted + Slot: unit match passes, stranger fails', function () {
    $earl = Unit::factory()->withSides()->commander()->create();
    $other = Unit::factory()->withSides()->titan()->create();

    $asset = Asset::factory()->restrictedByUnit($earl)->slot('Head')->create();

    expect($asset->canAttachTo($earl))->toBeTrue()
        ->and($asset->canAttachTo($other))->toBeFalse();
});
