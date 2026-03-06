<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Scheme;
use App\Models\Strategy;

it('displays the scenario generator page with default season', function () {
    $season = PoolSeasonEnum::defaultSeason();
    Strategy::factory()->count(2)->create(['season' => $season]);
    Scheme::factory()->count(5)->create(['season' => $season]);

    $response = $this->get(route('tools.scenario_generator'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/ScenarioGenerator')
            ->has('season', fn ($s) => $s
                ->where('value', $season->value)
                ->where('label', $season->label())
            )
            ->has('seasons')
            ->has('deployments', 4)
            ->has('strategies', 2)
            ->has('schemes', 5)
        );
});

it('accepts a season query parameter', function () {
    $season = PoolSeasonEnum::GainingGrounds0;
    Strategy::factory()->count(3)->create(['season' => $season]);
    Scheme::factory()->count(4)->create(['season' => $season]);

    $response = $this->get(route('tools.scenario_generator', ['season' => $season->value]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tools/ScenarioGenerator')
            ->has('season', fn ($s) => $s
                ->where('value', $season->value)
                ->where('label', $season->label())
            )
            ->has('deployments', 4)
            ->has('strategies', 3)
            ->has('schemes', 4)
        );
});

it('returns 404 for invalid season query parameter', function () {
    $response = $this->get(route('tools.scenario_generator', ['season' => 'invalid-season']));

    $response->assertNotFound();
});

it('always passes all 4 deployments', function () {
    $response = $this->get(route('tools.scenario_generator'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('deployments', 4)
        );
});
