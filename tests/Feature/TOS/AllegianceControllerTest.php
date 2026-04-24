<?php

use App\Models\TOS\Allegiance;

it('renders the TOS landing hub', function () {
    $earth = Allegiance::factory()->earth()->create(['name' => 'King\'s Empire']);
    $synd = Allegiance::factory()->malifaux()->syndicate()->create(['name' => 'Court of Two']);

    $this->get(route('tos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Index')
            ->has('allegiances', 1)
            ->has('syndicates', 1)
            ->where('allegiances.0.name', $earth->name)
            ->where('syndicates.0.name', $synd->name)
        );
});

it('renders the public allegiances index', function () {
    Allegiance::factory()->count(3)->create();

    $this->get(route('tos.allegiances.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Allegiances/Index')
            ->has('allegiances.data', 3)
        );
});

it('renders a single allegiance view by slug', function () {
    $a = Allegiance::factory()->create(['slug' => 'kings-empire', 'name' => "King's Empire"]);

    $this->get(route('tos.allegiances.view', $a->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Allegiances/View')
            ->where('allegiance.slug', 'kings-empire')
            ->where('allegiance.name', "King's Empire")
        );
});

it('returns 404 for an unknown allegiance slug', function () {
    $this->get(route('tos.allegiances.view', 'does-not-exist'))->assertNotFound();
});
