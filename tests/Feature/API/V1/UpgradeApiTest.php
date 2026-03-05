<?php

use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Upgrade;

it('lists upgrades with pagination', function () {
    Upgrade::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/upgrades');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('filters upgrades by domain', function () {
    Upgrade::factory()->create(['domain' => UpgradeDomainTypeEnum::Crew]);
    Upgrade::factory()->create(['domain' => UpgradeDomainTypeEnum::Character]);

    $response = $this->getJson('/api/v1/upgrades?domain=crew');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.domain'))->toBe('crew');
});

it('searches upgrades by name', function () {
    Upgrade::factory()->create(['name' => 'Spirit Weapon']);
    Upgrade::factory()->create(['name' => 'Dark Bargain']);

    $response = $this->getJson('/api/v1/upgrades?search=Spirit');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single upgrade by slug', function () {
    $upgrade = Upgrade::factory()->create();

    $response = $this->getJson("/api/v1/upgrades/{$upgrade->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $upgrade->id);
});

it('returns 404 for missing upgrade', function () {
    $this->getJson('/api/v1/upgrades/nonexistent-slug')
        ->assertNotFound();
});
