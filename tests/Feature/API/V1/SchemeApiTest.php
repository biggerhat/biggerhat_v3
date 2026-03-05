<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Scheme;

it('lists schemes with pagination', function () {
    Scheme::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/schemes');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('filters schemes by season', function () {
    Scheme::factory()->create(['season' => PoolSeasonEnum::Core]);
    Scheme::factory()->create(['season' => PoolSeasonEnum::GainingGrounds1]);

    $response = $this->getJson('/api/v1/schemes?season=core');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.season'))->toBe('core');
});

it('searches schemes by name', function () {
    Scheme::factory()->create(['name' => 'Assassinate']);
    Scheme::factory()->create(['name' => 'Detonate Charges']);

    $response = $this->getJson('/api/v1/schemes?search=Assassinate');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single scheme by slug', function () {
    $scheme = Scheme::factory()->create();

    $response = $this->getJson("/api/v1/schemes/{$scheme->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $scheme->id);
});

it('returns 404 for missing scheme', function () {
    $this->getJson('/api/v1/schemes/nonexistent-slug')
        ->assertNotFound();
});
