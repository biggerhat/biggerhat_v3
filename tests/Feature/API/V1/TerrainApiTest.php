<?php

use App\Models\Marker;
use App\Models\Terrain;

it('lists terrains with pagination', function () {
    Terrain::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/terrains');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('searches terrains by name', function () {
    Terrain::factory()->create(['name' => 'Dense']);
    Terrain::factory()->create(['name' => 'Hazardous']);

    $response = $this->getJson('/api/v1/terrains?search=Dense');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single terrain by slug with markers', function () {
    $terrain = Terrain::factory()->create();
    $marker = Marker::factory()->create();
    $terrain->markers()->attach($marker);

    $response = $this->getJson("/api/v1/terrains/{$terrain->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $terrain->id)
        ->assertJsonStructure(['data' => ['markers']]);
});

it('returns 404 for missing terrain', function () {
    $this->getJson('/api/v1/terrains/nonexistent-slug')
        ->assertNotFound();
});
