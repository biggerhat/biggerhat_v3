<?php

use App\Enums\FactionEnum;

it('lists all factions', function () {
    $response = $this->getJson('/api/v1/factions');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['value', 'label', 'color', 'logo']]]);

    expect($response->json('data'))->toHaveCount(count(FactionEnum::cases()));
});

it('shows a single faction with stats', function () {
    $response = $this->getJson('/api/v1/factions/guild');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['value', 'label', 'color', 'logo', 'stats']])
        ->assertJsonPath('data.value', 'guild');
});

it('returns 404 for invalid faction', function () {
    $this->getJson('/api/v1/factions/nonexistent')
        ->assertNotFound();
});
