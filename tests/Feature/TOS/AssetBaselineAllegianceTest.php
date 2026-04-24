<?php

use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Unit;

it('canAttachTo rejects units that do not share any Allegiance with the Asset', function () {
    $ke = Allegiance::factory()->earth()->create();
    $cult = Allegiance::factory()->malifaux()->create();

    $keUnit = Unit::factory()->withSides()->commander()->create();
    $keUnit->allegiances()->attach($ke->id);

    $cultUnit = Unit::factory()->withSides()->commander()->create();
    $cultUnit->allegiances()->attach($cult->id);

    $asset = Asset::factory()->forAllegiance($ke)->create();

    expect($asset->canAttachTo($keUnit))->toBeTrue()
        ->and($asset->canAttachTo($cultUnit))->toBeFalse();
});

it('canAttachTo still allows Asset with no Allegiance to pass the baseline', function () {
    // Universal-Allegiance Assets (rare) bypass the baseline match.
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->attach(Allegiance::factory()->create()->id);

    $asset = Asset::factory()->unique()->create();

    expect($asset->canAttachTo($unit))->toBeTrue();
});

it('baseline allegiance match runs BEFORE individual Limit rules', function () {
    $ke = Allegiance::factory()->earth()->create();
    $cult = Allegiance::factory()->malifaux()->create();

    $cultUnit = Unit::factory()->withSides()->commander()->create();
    $cultUnit->allegiances()->attach($cult->id);

    // Asset is anchored to KE with a unit-type Restricted limit for Commander.
    // The unit IS a Commander, but it's in Cult — baseline rejects.
    $asset = Asset::factory()
        ->forAllegiance($ke)
        ->restrictedByUnitType('commander')
        ->create();

    expect($asset->canAttachTo($cultUnit))->toBeFalse();
});
