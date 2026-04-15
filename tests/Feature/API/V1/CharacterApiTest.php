<?php

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;

it('lists characters with pagination', function () {
    Character::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/characters');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('excludes hidden characters from the list', function () {
    Character::factory()->create(['is_hidden' => false]);
    Character::factory()->create(['is_hidden' => true]);

    $response = $this->getJson('/api/v1/characters');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('filters characters by faction', function () {
    Character::factory()->create(['faction' => FactionEnum::Guild]);
    Character::factory()->create(['faction' => FactionEnum::Bayou]);

    $response = $this->getJson('/api/v1/characters?faction=guild');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.faction'))->toBe('guild');
});

it('filters characters by station', function () {
    Character::factory()->create(['station' => CharacterStationEnum::Master]);
    Character::factory()->create(['station' => CharacterStationEnum::Minion]);

    $response = $this->getJson('/api/v1/characters?station=master');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.station'))->toBe('master');
});

it('searches characters by name', function () {
    Character::factory()->create(['name' => 'Lady Justice', 'display_name' => 'Lady Justice', 'slug' => 'lady-justice']);
    Character::factory()->create(['name' => 'Rasputina', 'display_name' => 'Rasputina', 'slug' => 'rasputina']);

    $response = $this->getJson('/api/v1/characters?search=Lady');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Lady Justice');
});

it('shows a single character by slug', function () {
    $character = Character::factory()->create(['is_hidden' => false]);
    Miniature::factory()->for($character)->create();
    $keyword = Keyword::factory()->create();
    $character->keywords()->attach($keyword);

    $response = $this->getJson("/api/v1/characters/{$character->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $character->id)
        ->assertJsonPath('data.slug', $character->slug)
        ->assertJsonStructure(['data' => ['miniatures', 'keywords']]);
});

it('returns 404 for hidden character', function () {
    $character = Character::factory()->create(['is_hidden' => true]);

    $this->getJson("/api/v1/characters/{$character->slug}")
        ->assertNotFound();
});

it('returns 404 for missing character', function () {
    $this->getJson('/api/v1/characters/nonexistent-slug')
        ->assertNotFound();
});

it('prioritizes exact name matches over substring matches', function () {
    Character::factory()->create(['name' => 'Susan Sue-Smith']);
    Character::factory()->create(['name' => 'Suesan']);
    Character::factory()->create(['name' => 'Sue']);
    Character::factory()->create(['name' => 'Old Man Suey']);

    $response = $this->getJson('/api/v1/characters?search=Sue');

    $response->assertOk();
    // Exact "Sue" match should be first
    expect($response->json('data.0.name'))->toBe('Sue');
    // All 4 should be returned
    expect($response->json('data'))->toHaveCount(4);
});

it('prioritizes exact match case-insensitively', function () {
    Character::factory()->create(['name' => 'Bob The Great']);
    Character::factory()->create(['name' => 'Bob']);

    $response = $this->getJson('/api/v1/characters?search=bob');

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('Bob');
});
