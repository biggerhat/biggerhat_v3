<?php

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\CrewBuild;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('reports public crews and the most-used Masters on the faction page', function () {
    $popular = Character::factory()->create(['faction' => FactionEnum::Guild]);
    $other = Character::factory()->create(['faction' => FactionEnum::Guild]);

    CrewBuild::factory()->count(2)->create(['faction' => FactionEnum::Guild->value, 'master_id' => $popular->id, 'is_public' => true, 'is_archived' => false]);
    CrewBuild::factory()->create(['faction' => FactionEnum::Guild->value, 'master_id' => $other->id, 'is_public' => true, 'is_archived' => false]);
    // Not counted: private, archived and other-faction builds.
    CrewBuild::factory()->create(['faction' => FactionEnum::Guild->value, 'master_id' => $other->id, 'is_public' => false]);
    CrewBuild::factory()->create(['faction' => FactionEnum::Guild->value, 'master_id' => $other->id, 'is_public' => true, 'is_archived' => true]);
    CrewBuild::factory()->create(['faction' => FactionEnum::Bayou->value, 'master_id' => $other->id, 'is_public' => true, 'is_archived' => false]);

    $this->get(route('factions.view', 'guild'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Factions/View')
            ->where('community_crews.total', 3)
            ->where('community_crews.masters.0.slug', $popular->slug)
            ->where('community_crews.masters.0.count', 2)
            ->has('community_crews.masters', 2));
});

it('returns zero community crews for a faction nobody has built', function () {
    $this->get(route('factions.view', 'guild'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('community_crews.total', 0)->where('community_crews.masters', []));
});
