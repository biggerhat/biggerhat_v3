<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Scheme;
use App\Models\Strategy;

it('redirects seasons index to default season', function () {
    $response = $this->get(route('seasons.index'));

    $response->assertRedirect(route('seasons.view', PoolSeasonEnum::defaultSeason()->value));
});

it('displays the season view page with strategies and schemes', function () {
    $season = PoolSeasonEnum::GainingGrounds0;
    Strategy::factory()->count(2)->create(['season' => $season]);
    Scheme::factory()->count(3)->create(['season' => $season]);

    $response = $this->get(route('seasons.view', $season->value));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Seasons/Index')
            ->has('season', fn ($s) => $s
                ->where('value', $season->value)
                ->where('label', $season->label())
            )
            ->has('seasons')
            ->has('deployments', 4)
            ->has('strategies', 2)
            ->has('schemes', 3)
        );
});

it('displays the season view with no content', function () {
    $response = $this->get(route('seasons.view', PoolSeasonEnum::GainingGrounds0->value));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Seasons/Index')
            ->has('deployments', 4)
            ->has('strategies', 0)
            ->has('schemes', 0)
        );
});

it('displays a scheme view page', function () {
    $scheme = Scheme::factory()->create();

    $response = $this->get(route('schemes.view', $scheme));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Seasons/SchemeView')
            ->has('scheme', fn ($s) => $s
                ->where('id', $scheme->id)
                ->where('name', $scheme->name)
                ->where('slug', $scheme->slug)
                ->etc()
            )
        );
});

it('displays a strategy view page', function () {
    $strategy = Strategy::factory()->create();

    $response = $this->get(route('strategies.view', $strategy));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Seasons/StrategyView')
            ->has('strategy', fn ($s) => $s
                ->where('id', $strategy->id)
                ->where('name', $strategy->name)
                ->where('slug', $strategy->slug)
                ->etc()
            )
        );
});

it('returns 404 for invalid season', function () {
    $response = $this->get('/seasons/invalid-season');

    $response->assertNotFound();
});

it('returns 404 for nonexistent scheme', function () {
    $response = $this->get('/schemes/nonexistent-slug');

    $response->assertNotFound();
});

it('returns 404 for nonexistent strategy', function () {
    $response = $this->get('/strategies/nonexistent-slug');

    $response->assertNotFound();
});

it('shows next schemes on scheme view', function () {
    $nextScheme = Scheme::factory()->create();
    $scheme = Scheme::factory()->create(['next_scheme_one_id' => $nextScheme->id]);

    $response = $this->get(route('schemes.view', $scheme));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Seasons/SchemeView')
            ->has('scheme.next_schemes', 1)
        );
});
