<?php

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Upgrade;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    // Ensure every Character has at least one miniature so the controller's
    // standard scope returns it.
    $this->makeCharacter = fn (array $attrs = []) => tap(Character::factory()->create($attrs), function ($c) {
        Miniature::factory()->create(['character_id' => $c->id]);
    });
});

// The upgrade query is only triggered when a "could be an upgrade" filter is
// present — name/description/action/etc. We use a shared `name` filter as
// the gate in these tests so the bug surfaces consistently.

it('respects the faction filter on upgrades', function () {
    Upgrade::factory()->create(['name' => 'Glowing Saber', 'faction' => FactionEnum::Arcanists]);
    Upgrade::factory()->create(['name' => 'Glowing Saber Bayou', 'faction' => FactionEnum::Bayou]);

    $response = $this->get(route('search.view', [
        'name' => 'Glowing',
        'faction' => FactionEnum::Arcanists->value,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Search/View')
        ->where('result_breakdown.upgrades', 1)
    );

    $upgradeNames = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'upgrade')
        ->pluck('name')
        ->all();

    expect($upgradeNames)->toBe(['Glowing Saber']);
});

it('respects faction_exclude on upgrades', function () {
    Upgrade::factory()->create(['name' => 'Trinket Arc', 'faction' => FactionEnum::Arcanists]);
    Upgrade::factory()->create(['name' => 'Trinket Guild', 'faction' => FactionEnum::Guild]);

    $response = $this->get(route('search.view', [
        'name' => 'Trinket',
        'faction_exclude' => FactionEnum::Arcanists->value,
    ]));

    $response->assertOk();

    $upgradeNames = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'upgrade')
        ->pluck('name')
        ->all();

    expect($upgradeNames)->toBe(['Trinket Guild']);
});

it('respects the keyword filter on upgrades with default AND logic', function () {
    // Keyword's UsesSlugName trait regenerates slug from name on save, so we
    // set name (rather than slug) to control the slug deterministically.
    $student = Keyword::factory()->create(['name' => 'student']);
    $performer = Keyword::factory()->create(['name' => 'performer']);

    $matches = Upgrade::factory()->create(['name' => 'Top Hat A']);
    $matches->keywords()->attach([$student->id, $performer->id]);

    $partialMatch = Upgrade::factory()->create(['name' => 'Top Hat B']);
    $partialMatch->keywords()->attach($student->id);

    $noKeyword = Upgrade::factory()->create(['name' => 'Top Hat C']);

    $response = $this->get(route('search.view', [
        'name' => 'Top Hat',
        'keyword' => 'student,performer',
    ]));

    $response->assertOk();
    $upgradeNames = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'upgrade')
        ->pluck('name')
        ->all();

    expect($upgradeNames)->toBe(['Top Hat A']);
});

it('respects the keyword filter on upgrades with OR logic', function () {
    $student = Keyword::factory()->create(['name' => 'student']);
    $performer = Keyword::factory()->create(['name' => 'performer']);

    $a = Upgrade::factory()->create(['name' => 'Or A']);
    $a->keywords()->attach($student->id);

    $b = Upgrade::factory()->create(['name' => 'Or B']);
    $b->keywords()->attach($performer->id);

    Upgrade::factory()->create(['name' => 'Or C']); // no keyword — excluded

    $response = $this->get(route('search.view', [
        'name' => 'Or',
        'keyword' => 'student,performer',
        'keyword_logic' => 'or',
    ]));

    $response->assertOk();
    $upgradeNames = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'upgrade')
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($upgradeNames)->toBe(['Or A', 'Or B']);
});

it('respects keyword_exclude on upgrades', function () {
    $banned = Keyword::factory()->create(['name' => 'banned-kw']);

    $kept = Upgrade::factory()->create(['name' => 'Excl Keep']);
    $dropped = Upgrade::factory()->create(['name' => 'Excl Drop']);
    $dropped->keywords()->attach($banned->id);

    $response = $this->get(route('search.view', [
        'name' => 'Excl',
        'keyword_exclude' => 'banned-kw',
    ]));

    $response->assertOk();
    $upgradeNames = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'upgrade')
        ->pluck('name')
        ->all();

    expect($upgradeNames)->toBe(['Excl Keep']);
});

it('short-circuits the upgrade query when exclude_upgrades=1', function () {
    // Both a character and an upgrade match the name search; with the toggle
    // on we want the character but not the upgrade.
    ($this->makeCharacter)(['name' => 'Shared Name', 'display_name' => 'Shared Name', 'slug' => 'shared-name']);
    Upgrade::factory()->create(['name' => 'Shared Name Upgrade']);

    $response = $this->get(route('search.view', [
        'name' => 'Shared',
        'exclude_upgrades' => '1',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('result_breakdown.upgrades', 0)
    );

    // And without the toggle, the upgrade comes back.
    $loose = $this->get(route('search.view', ['name' => 'Shared']));
    $loose->assertInertia(fn (AssertableInertia $page) => $page
        ->where('result_breakdown.upgrades', 1)
    );
});

it('skips the upgrade query entirely when only faction is provided (existing behavior preserved)', function () {
    // The fix should NOT pull upgrades into a bare faction browse — only when
    // a text/action/ability/trigger/token/marker filter establishes the user
    // is searching for an entity.
    Upgrade::factory()->create(['faction' => FactionEnum::Arcanists, 'name' => 'Faction Only']);
    ($this->makeCharacter)(['faction' => FactionEnum::Arcanists]);

    $response = $this->get(route('search.view', ['faction' => FactionEnum::Arcanists->value]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('result_breakdown.upgrades', 0)
    );
});
