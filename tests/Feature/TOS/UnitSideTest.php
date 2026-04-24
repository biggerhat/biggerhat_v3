<?php

use App\Enums\TOS\UnitSideEnum;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSide;

it('enforces unique (unit_id, side) constraint', function () {
    $unit = Unit::factory()->create();

    UnitSide::create([
        'unit_id' => $unit->id,
        'side' => UnitSideEnum::Standard->value,
        'speed' => 5,
        'defense' => 4,
        'willpower' => 4,
        'armor' => 1,
    ]);

    expect(fn () => UnitSide::create([
        'unit_id' => $unit->id,
        'side' => UnitSideEnum::Standard->value,
        'speed' => 6,
        'defense' => 5,
        'willpower' => 5,
        'armor' => 2,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('side enum casts on retrieval', function () {
    $unit = Unit::factory()->withSides()->create();

    $standard = $unit->standardSide();
    $glory = $unit->glorySide();

    expect($standard->side)->toBeInstanceOf(UnitSideEnum::class)
        ->and($standard->side)->toBe(UnitSideEnum::Standard)
        ->and($glory->side)->toBe(UnitSideEnum::Glory);
});

it('factory invariant: withSides() always yields exactly two sides', function () {
    $units = Unit::factory()->withSides()->count(5)->create();

    foreach ($units as $u) {
        expect($u->fresh()->sides()->count())->toBe(2);
    }
});
