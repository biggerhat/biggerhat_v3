<?php

use App\Models\Character;
use App\Models\Lore;
use App\Models\LoreMedia;

it('lists lore entries with pagination', function () {
    Lore::factory()->count(2)->create();

    $this->getJson('/api/v1/lore')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonCount(2, 'data');
});

it('filters lore entries by linked character', function () {
    $character = Character::factory()->create();
    $linked = Lore::factory()->create();
    $linked->characters()->attach($character);
    Lore::factory()->create();

    $this->getJson("/api/v1/lore?character={$character->slug}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $linked->id);
});

it('shows a single lore entry by slug', function () {
    $lore = Lore::factory()->create();

    $this->getJson("/api/v1/lore/{$lore->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $lore->id);
});

it('lists lore media', function () {
    LoreMedia::factory()->count(2)->create();

    $this->getJson('/api/v1/lore-media')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows a single lore media entry by slug', function () {
    $media = LoreMedia::factory()->create();

    $this->getJson("/api/v1/lore-media/{$media->slug}")
        ->assertOk()
        ->assertJsonPath('data.id', $media->id);
});
