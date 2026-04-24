<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;

it('factory creates an Earth allegiance by default', function () {
    $a = Allegiance::factory()->create();

    expect($a->type)->toBe(AllegianceTypeEnum::Earth)
        ->and($a->is_syndicate)->toBeFalse();
});

it('syndicate state flips the flag', function () {
    $a = Allegiance::factory()->syndicate()->create();

    expect($a->is_syndicate)->toBeTrue();
});

it('malifaux state sets the type', function () {
    $a = Allegiance::factory()->malifaux()->create();

    expect($a->type)->toBe(AllegianceTypeEnum::Malifaux);
});

it('syndicates scope returns only syndicates', function () {
    Allegiance::factory()->count(3)->create();
    Allegiance::factory()->syndicate()->count(2)->create();

    expect(Allegiance::syndicates()->count())->toBe(2)
        ->and(Allegiance::mainAllegiances()->count())->toBe(3);
});

it('ofType scope filters by AllegianceTypeEnum', function () {
    Allegiance::factory()->earth()->count(2)->create();
    Allegiance::factory()->malifaux()->count(3)->create();

    expect(Allegiance::ofType(AllegianceTypeEnum::Earth)->count())->toBe(2)
        ->and(Allegiance::ofType('malifaux')->count())->toBe(3);
});

it('persists with the slug, name, type, and is_syndicate columns intact', function () {
    $a = Allegiance::factory()->malifaux()->syndicate()->create([
        'slug' => 'test-syndicate',
        'name' => 'Test Syndicate',
    ]);

    $reloaded = Allegiance::find($a->id);

    expect($reloaded->slug)->toBe('test-syndicate')
        ->and($reloaded->name)->toBe('Test Syndicate')
        ->and($reloaded->type)->toBe(AllegianceTypeEnum::Malifaux)
        ->and($reloaded->is_syndicate)->toBeTrue();
});
