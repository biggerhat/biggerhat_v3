<?php

use App\Models\Marker;

it('lists markers with pagination', function () {
    Marker::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/markers');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('searches markers by name', function () {
    Marker::factory()->create(['name' => 'Scrap']);
    Marker::factory()->create(['name' => 'Corpse']);

    $response = $this->getJson('/api/v1/markers?search=Scrap');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single marker by slug', function () {
    $marker = Marker::factory()->create();

    $response = $this->getJson("/api/v1/markers/{$marker->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $marker->id)
        ->assertJsonStructure(['data' => ['terrains']]);
});

it('returns 404 for missing marker', function () {
    $this->getJson('/api/v1/markers/nonexistent-slug')
        ->assertNotFound();
});
