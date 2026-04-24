<?php

use App\Models\TOS\Asset;
use App\Models\TOS\Stratagem;

it('renders the assets index with allegiances and limits eager-loaded', function () {
    Asset::factory()->unique()->create(['name' => 'Asset A']);
    Asset::factory()->slot('Head')->create(['name' => 'Asset B']);

    $this->get(route('tos.assets.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Assets/Index')->has('assets.data', 2));
});

it('renders a single asset by slug with limits and pivots', function () {
    $asset = Asset::factory()->slot('Head')->unique()->create(['slug' => 'headscope', 'name' => 'Head Scope']);

    $this->get(route('tos.assets.view', $asset->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Assets/View')
            ->where('asset.name', 'Head Scope')
            ->has('asset.limits', 2)
        );
});

it('404s on an unknown asset slug', function () {
    $this->get(route('tos.assets.view', 'nope'))->assertNotFound();
});

it('renders the stratagems index', function () {
    Stratagem::factory()->count(3)->create();

    $this->get(route('tos.stratagems.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Stratagems/Index')->has('stratagems.data', 3));
});

it('renders a single stratagem by slug', function () {
    $s = Stratagem::factory()->create(['slug' => 'volley', 'name' => 'Volley Fire']);

    $this->get(route('tos.stratagems.view', $s->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Stratagems/View')->where('stratagem.name', 'Volley Fire'));
});

it('404s on an unknown stratagem slug', function () {
    $this->get(route('tos.stratagems.view', 'nope'))->assertNotFound();
});
