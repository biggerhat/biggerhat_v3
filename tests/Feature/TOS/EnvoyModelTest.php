<?php

use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Envoy;

it('belongs to its parent Allegiance (typically a Syndicate)', function () {
    $syndicate = Allegiance::factory()->malifaux()->syndicate()->create();
    $envoy = Envoy::factory()->forAllegiance($syndicate)->create();

    expect($envoy->fresh()->allegiance->id)->toBe($syndicate->id)
        ->and($envoy->fresh()->allegiance->is_syndicate)->toBeTrue();
});

it('casts restriction to EnvoyRestrictionEnum', function () {
    $envoy = Envoy::factory()->create(['restriction' => 'earth']);

    expect($envoy->fresh()->restriction)->toBe(EnvoyRestrictionEnum::Earth);
});

it('syncs abilities via the pivot', function () {
    $envoy = Envoy::factory()->create();
    $a = Ability::factory()->general()->create();

    $envoy->abilities()->attach($a->id, ['sort_order' => 0]);

    expect($envoy->fresh()->abilities->pluck('id'))->toContain($a->id);
});

it('withAbilities() factory state attaches the requested ability count', function () {
    $envoy = Envoy::factory()->withAbilities(2)->create();

    expect($envoy->fresh()->abilities->count())->toBe(2);
});
