<?php

use App\Models\Ability;

it('matches both "remove a token" and "remove up to one token" when the user types "remove * token"', function () {
    Ability::factory()->create(['name' => 'Drainer A', 'description' => 'When this happens, remove a token from the target.']);
    Ability::factory()->create(['name' => 'Drainer B', 'description' => 'When this happens, remove up to one token from the target.']);
    Ability::factory()->create(['name' => 'Unrelated', 'description' => 'Push this model 3".']);

    $response = $this->get(route('abilities.index', ['description' => 'remove * token']));

    $response->assertOk();

    $names = collect($response->viewData('page')['props']['abilities']['data'])->pluck('name')->all();

    expect($names)->toContain('Drainer A')
        ->toContain('Drainer B')
        ->not->toContain('Unrelated');
});

it('treats a plain phrase as a substring match (no wildcards)', function () {
    Ability::factory()->create(['name' => 'Bleeder', 'description' => 'Inflicts a burning token on the target.']);
    Ability::factory()->create(['name' => 'Healer', 'description' => 'Heals one damage.']);

    $response = $this->get(route('abilities.index', ['description' => 'burning token']));

    $response->assertOk();

    $names = collect($response->viewData('page')['props']['abilities']['data'])->pluck('name')->all();

    expect($names)->toContain('Bleeder')
        ->not->toContain('Healer');
});
