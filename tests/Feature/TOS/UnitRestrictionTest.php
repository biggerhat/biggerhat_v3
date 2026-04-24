<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Unit;

it('restriction column casts to AllegianceTypeEnum', function () {
    $unit = Unit::factory()->withSides()->neutralFor(AllegianceTypeEnum::Earth)->create();

    expect($unit->fresh()->restriction)->toBe(AllegianceTypeEnum::Earth);
});

it('hireableInto returns units directly attached to the Allegiance', function () {
    $ke = Allegiance::factory()->earth()->create();
    $attached = Unit::factory()->withSides()->create(['name' => 'Royal Rifle Corps']);
    $attached->allegiances()->attach($ke->id);

    $unattached = Unit::factory()->withSides()->create(['name' => 'Bokor']);

    $names = Unit::hireableInto($ke)->pluck('name')->all();

    expect($names)->toContain('Royal Rifle Corps')->and($names)->not->toContain('Bokor');
});

it('hireableInto returns neutral units that match the allegiance type', function () {
    $ke = Allegiance::factory()->earth()->create();
    $cotbm = Allegiance::factory()->malifaux()->create();

    $neutralEarth = Unit::factory()->withSides()->neutralFor(AllegianceTypeEnum::Earth)->create(['name' => 'Neutral Earth Unit']);
    $neutralMalifaux = Unit::factory()->withSides()->neutralFor(AllegianceTypeEnum::Malifaux)->create(['name' => 'Neutral Malifaux Unit']);

    $earthPool = Unit::hireableInto($ke)->pluck('name')->all();
    $malifauxPool = Unit::hireableInto($cotbm)->pluck('name')->all();

    expect($earthPool)->toContain('Neutral Earth Unit')
        ->and($earthPool)->not->toContain('Neutral Malifaux Unit')
        ->and($malifauxPool)->toContain('Neutral Malifaux Unit')
        ->and($malifauxPool)->not->toContain('Neutral Earth Unit');
});

it('admin store accepts a neutral unit with no allegiance attachments', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $admin = \App\Models\User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)->post(route('admin.tos.units.store'), [
        'name' => 'Neutral Soldier',
        'scrip' => 4,
        'restriction' => AllegianceTypeEnum::Earth->value,
        'allegiance_ids' => [],
        'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
            ['side' => 'glory', 'speed' => 6, 'defense' => 5, 'willpower' => 5, 'armor' => 2],
        ],
    ])->assertRedirect();

    $u = Unit::where('name', 'Neutral Soldier')->first();
    expect($u)->not->toBeNull()
        ->and($u->restriction)->toBe(AllegianceTypeEnum::Earth)
        ->and($u->allegiances)->toBeEmpty();
});

it('admin store rejects a unit with neither allegiance nor restriction', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $admin = \App\Models\User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)->postJson(route('admin.tos.units.store'), [
        'name' => 'Floating Unit',
        'scrip' => 4,
        'allegiance_ids' => [],
        'sides' => [
            ['side' => 'standard', 'speed' => 5, 'defense' => 4, 'willpower' => 4, 'armor' => 1],
            ['side' => 'glory', 'speed' => 6, 'defense' => 5, 'willpower' => 5, 'armor' => 2],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['allegiance_ids', 'restriction']);
});
