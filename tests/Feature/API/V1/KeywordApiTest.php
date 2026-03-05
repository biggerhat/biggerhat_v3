<?php

use App\Models\Character;
use App\Models\Keyword;

it('lists keywords with pagination', function () {
    Keyword::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/keywords');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(3);
});

it('searches keywords by name', function () {
    Keyword::factory()->create(['name' => 'Undead']);
    Keyword::factory()->create(['name' => 'Beast']);

    $response = $this->getJson('/api/v1/keywords?search=Undead');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Undead');
});

it('shows a single keyword by slug with characters', function () {
    $keyword = Keyword::factory()->create();
    $character = Character::factory()->create(['is_hidden' => false]);
    $keyword->characters()->attach($character);

    $response = $this->getJson("/api/v1/keywords/{$keyword->slug}");

    $response->assertOk()
        ->assertJsonPath('data.id', $keyword->id)
        ->assertJsonStructure(['data' => ['characters']]);
});

it('returns 404 for missing keyword', function () {
    $this->getJson('/api/v1/keywords/nonexistent-slug')
        ->assertNotFound();
});
