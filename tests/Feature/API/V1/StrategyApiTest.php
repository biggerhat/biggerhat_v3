<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Strategy;

it('lists strategies with pagination', function () {
    Strategy::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/strategies');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('filters strategies by season', function () {
    Strategy::factory()->create(['season' => PoolSeasonEnum::Core]);
    Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds1]);

    $response = $this->getJson('/api/v1/strategies?season=core');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.season'))->toBe('core');
});

it('searches strategies by name', function () {
    Strategy::factory()->create(['name' => 'Turf War']);
    Strategy::factory()->create(['name' => 'Corrupted Ley Lines']);

    $response = $this->getJson('/api/v1/strategies?search=Turf');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single strategy by slug', function () {
    $strategy = Strategy::factory()->create();

    $response = $this->getJson("/api/v1/strategies/{$strategy->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $strategy->id);
});

it('returns 404 for missing strategy', function () {
    $this->getJson('/api/v1/strategies/nonexistent-slug')
        ->assertNotFound();
});
