<?php

use App\Models\Character;
use App\Models\Miniature;

it('keeps the (Sculpt N) suffixes when a miniature is saved with its base name rebuilt', function () {
    $character = Character::factory()->create(['name' => 'Piglet', 'title' => null]);
    $one = Miniature::factory()->create(['character_id' => $character->id, 'display_name' => 'Piglet', 'slug' => 'piglet']);
    $two = Miniature::factory()->create(['character_id' => $character->id, 'display_name' => 'Piglet', 'slug' => 'piglet']);

    expect($one->fresh()->display_name)->toBe('Piglet')->and($two->fresh()->display_name)->toBe('Piglet (Sculpt 2)');

    // What the admin form does on every save (an errata edit that changes only an image, say): the name comes back without its suffix.
    $two->fresh()->update(['display_name' => 'Piglet', 'slug' => 'piglet', 'front_image' => 'characters/1/new.jpg']);

    expect($two->fresh()->display_name)->toBe('Piglet (Sculpt 2)')->and($one->fresh()->display_name)->toBe('Piglet');
});
