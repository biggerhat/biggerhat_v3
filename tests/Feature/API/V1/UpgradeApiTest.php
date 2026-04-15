<?php

use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Character;
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

it('searches crew upgrades by linked character display_name', function () {
    $character = Character::factory()->create(['name' => 'Maxine Agassiz']);
    $upgrade = Upgrade::factory()->create(['name' => 'Age of Inspiration', 'domain' => UpgradeDomainTypeEnum::Crew]);
    $character->upgrades()->attach($upgrade);

    Upgrade::factory()->create(['name' => 'Other Crew Card', 'domain' => UpgradeDomainTypeEnum::Crew]);

    $response = $this->getJson('/api/v1/upgrades?search=Maxine&domain=crew');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toContain('Age of Inspiration');
    expect($names)->not->toContain('Other Crew Card');
});

it('searches crew upgrades by linked character nicknames', function () {
    $character = Character::factory()->create(['name' => 'Some Master', 'nicknames' => 'Maxie']);
    $upgrade = Upgrade::factory()->create(['name' => 'Crew Card With Nickname Master', 'domain' => UpgradeDomainTypeEnum::Crew]);
    $character->upgrades()->attach($upgrade);

    $response = $this->getJson('/api/v1/upgrades?search=Maxie&domain=crew');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Crew Card With Nickname Master');
});

it('does not search by character name when domain is not crew', function () {
    $character = Character::factory()->create(['name' => 'Justice McMourning']);
    $upgrade = Upgrade::factory()->create(['name' => 'Some Character Upgrade', 'domain' => UpgradeDomainTypeEnum::Character]);
    $character->upgrades()->attach($upgrade);

    $response = $this->getJson('/api/v1/upgrades?search=Justice&domain=character');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});
