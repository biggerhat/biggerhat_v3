<?php

use App\Models\Ability;

it('lists abilities with pagination', function () {
    Ability::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/abilities');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('searches abilities by name', function () {
    Ability::factory()->create(['name' => 'Hard to Kill']);
    Ability::factory()->create(['name' => 'Terrifying']);

    $response = $this->getJson('/api/v1/abilities?search=Hard');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single ability by slug', function () {
    $ability = Ability::factory()->create();

    $response = $this->getJson("/api/v1/abilities/{$ability->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $ability->id);
});

it('returns 404 for missing ability', function () {
    $this->getJson('/api/v1/abilities/nonexistent-slug')
        ->assertNotFound();
});
