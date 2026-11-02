<?php

use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Cache::forget('command.catalog'));

it('serves the command palette catalog to guests as cacheable JSON', function () {
    $character = Character::factory()->create();
    Miniature::factory()->for($character)->create();

    $response = $this->getJson(route('command'))->assertOk();

    $response->assertJsonStructure(['factions', 'keywords', 'characters', 'miniatures', 'upgrades', 'packages', 'tos_allegiances', 'tos_units', 'tos_stratagems']);
    expect(collect($response->json('characters'))->pluck('name'))->toContain($character->display_name);
    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=300');
});

it('builds the catalog once and then serves it from cache', function () {
    $character = Character::factory()->create();
    Miniature::factory()->for($character)->create();

    $this->getJson(route('command'))->assertOk();

    $catalogQueries = 0;
    DB::listen(function ($query) use (&$catalogQueries) {
        if (str_contains($query->sql, 'from "characters"') || str_contains($query->sql, 'from `characters`')) {
            $catalogQueries++;
        }
    });

    $this->getJson(route('command'))->assertOk();

    expect($catalogQueries)->toBe(0);
});
