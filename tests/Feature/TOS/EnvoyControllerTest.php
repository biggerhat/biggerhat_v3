<?php

use App\Models\TOS\Envoy;

it('renders the envoys index', function () {
    Envoy::factory()->count(2)->create();

    $this->get(route('tos.envoys.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Envoys/Index')->has('envoys', 2));
});

it('renders a single envoy by slug', function () {
    $envoy = Envoy::factory()->withAbilities(1)->create(['slug' => 'court-envoy', 'name' => 'Court of Two Envoy']);

    $this->get(route('tos.envoys.view', $envoy->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Envoys/View')
            ->where('envoy.name', 'Court of Two Envoy')
            ->has('envoy.abilities', 1)
        );
});

it('404s on an unknown slug', function () {
    $this->get(route('tos.envoys.view', 'does-not-exist'))->assertNotFound();
});
