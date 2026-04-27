<?php

use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;

it('renders the search page with empty results when no query is given', function () {
    $this->get(route('tos.search'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Search/Index')
            ->where('name_search', '')
            ->where('results', [])
            ->has('all_types', 9)
        );
});

it('returns nothing for queries shorter than 2 chars', function () {
    Unit::factory()->withSides()->create(['name' => 'Royal Rifle Corps']);

    $this->get(route('tos.search', ['name_search' => 'R']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('results', []));
});

it('finds matches across multiple entity types', function () {
    $unit = Unit::factory()->withSides()->create(['name' => 'Searchable Unit']);
    UnitSculpt::factory()->forUnit($unit)->create();
    Ability::factory()->general()->create(['name' => 'Searchable Ability']);
    Allegiance::factory()->earth()->create(['name' => 'Searchable Allegiance']);

    $this->get(route('tos.search', ['name_search' => 'Searchable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Search/Index')
            ->where('name_search', 'Searchable')
            ->has('results', 3)
        );
});

it('respects the type filter', function () {
    Unit::factory()->withSides()->create(['name' => 'Filterable Unit']);
    Ability::factory()->general()->create(['name' => 'Filterable Ability']);

    $this->get(route('tos.search', ['name_search' => 'Filterable', 'types' => 'units']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.type', 'units')
        );
});

it('routes ability/action/special-rule results to their index pages with name_search', function () {
    $a = Ability::factory()->general()->create(['name' => 'Linkable Ability']);

    $this->get(route('tos.search', ['name_search' => 'Linkable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.type', 'abilities')
            ->where('results.0.url', fn (?string $url) => is_string($url) && str_contains($url, 'tos/abilities') && str_contains($url, 'name_search=Linkable'))
        );
});

it('still accepts the legacy q= query parameter as an alias for name_search', function () {
    Unit::factory()->withSides()->create(['name' => 'Legacy Query Unit']);
    UnitSculpt::factory()->forUnit(Unit::where('name', 'Legacy Query Unit')->first())->create();

    $this->get(route('tos.search', ['q' => 'Legacy Query']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('name_search', 'Legacy Query')
            ->has('results', 1)
        );
});

it('produces a snippet for matching body text', function () {
    Asset::factory()->create([
        'name' => 'Spyglass',
        'body' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
    ]);

    $this->get(route('tos.search', ['name_search' => 'Spyglass']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('results', 1)
            ->where('results.0.type', 'assets')
            ->where('results.0.name', 'Spyglass')
            ->where('results.0.snippet', fn (string $s) => str_contains($s, 'Lorem ipsum'))
        );
});
