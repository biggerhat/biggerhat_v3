<?php

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Miniature;

it('renders the picker page without rolling', function () {
    Character::factory()->count(3)->create();

    $response = $this->get(route('tools.random_character'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Tools/RandomCharacter')
        ->where('picked', null)
        ->has('factions')
        ->has('keywords')
        ->has('characteristics')
    );
});

it('rolls a character matching the selected faction', function () {
    $arc = Character::factory()->create(['faction' => FactionEnum::Arcanists]);
    Miniature::factory()->for($arc, 'character')->create();
    $bayou = Character::factory()->create(['faction' => FactionEnum::Bayou]);
    Miniature::factory()->for($bayou, 'character')->create();

    // Repeat to make sure the inRandomOrder pick consistently respects the filter.
    foreach (range(1, 5) as $_) {
        $response = $this->get(route('tools.random_character', ['roll' => 1, 'faction' => 'arcanists']));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('picked.id', $arc->id));
    }
});

it('returns the picked miniature payload shaped for CharacterCardView', function () {
    $character = Character::factory()->create();
    $mini = Miniature::factory()->for($character, 'character')->create();

    $response = $this->get(route('tools.random_character', ['roll' => 1]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('picked.id', $character->id)
        ->where('picked.slug', $character->slug)
        ->where('picked.miniature.id', $mini->id)
        ->where('picked.miniature.slug', $mini->slug)
        ->where('picked.miniature.character_id', $character->id),
    );
});

it('rolls a character matching the cost band', function () {
    $cheap = Character::factory()->create(['cost' => 4]);
    Miniature::factory()->for($cheap, 'character')->create();
    $expensive = Character::factory()->create(['cost' => 10]);
    Miniature::factory()->for($expensive, 'character')->create();

    $response = $this->get(route('tools.random_character', [
        'roll' => 1,
        'cost_min' => 7,
        'cost_max' => 12,
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('picked.id', $expensive->id));
});

it('rolls a character matching keyword AND characteristic filters', function () {
    $keyword = Keyword::factory()->create(['name' => 'Frostbite', 'slug' => 'frostbite']);
    $characteristic = Characteristic::factory()->create(['name' => 'Insignificant', 'slug' => 'insignificant']);

    $match = Character::factory()->create();
    Miniature::factory()->for($match, 'character')->create();
    $match->keywords()->attach($keyword);
    $match->characteristics()->attach($characteristic);

    // A character with the keyword but missing the characteristic — must be excluded.
    $partial = Character::factory()->create();
    Miniature::factory()->for($partial, 'character')->create();
    $partial->keywords()->attach($keyword);

    $response = $this->get(route('tools.random_character', [
        'roll' => 1,
        'keyword' => 'frostbite',
        'characteristic' => 'insignificant',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('picked.id', $match->id));
});

it('returns picked=null when no character matches the filters', function () {
    $arc = Character::factory()->create(['faction' => FactionEnum::Arcanists]);
    Miniature::factory()->for($arc, 'character')->create();

    $response = $this->get(route('tools.random_character', ['roll' => 1, 'faction' => 'guild']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('picked', null));
});
