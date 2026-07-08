<?php

use App\Models\Package;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Unit;

it('renders the TOS packages index with only TOS-flagged packages', function () {
    Package::factory()->tos()->count(2)->create();
    Package::factory()->create(); // malifaux — should not appear

    $this->get(route('tos.packages.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Packages/Index')->has('packages.data', 2));
});

it('filters the TOS packages index by allegiance', function () {
    $allegiance = Allegiance::factory()->create();
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->attach($allegiance->id);

    $matching = Package::factory()->tos()->create();
    $matching->tosUnits()->attach($unit->id, ['quantity' => 1]);

    Package::factory()->tos()->create();

    $this->get(route('tos.packages.index', ['allegiance' => $allegiance->slug]))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('packages.data', 1)->where('packages.data.0.id', $matching->id));
});

it('views a TOS package with its linked units', function () {
    $package = Package::factory()->tos()->create(['name' => 'Abyssinia Starter Box']);
    $unit = Unit::factory()->withSides()->create(['name' => 'Widow']);
    $package->tosUnits()->attach($unit->id, ['quantity' => 1]);

    $this->get(route('tos.packages.view', $package))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Packages/View')
            ->where('package.name', 'Abyssinia Starter Box')
            ->has('package.units', 1)
            ->where('package.units.0.name', 'Widow')
        );
});

it('returns 404 when viewing a Malifaux package through the TOS route', function () {
    $package = Package::factory()->create();

    $this->get(route('tos.packages.view', $package))->assertNotFound();
});

it('returns 404 when viewing a TOS package through the Malifaux route', function () {
    $package = Package::factory()->tos()->create();

    $this->get(route('packages.view', $package))->assertNotFound();
});

it('excludes TOS packages from the Malifaux packages index', function () {
    Package::factory()->create();
    Package::factory()->tos()->create();

    $this->get(route('packages.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('packages.data', 1));
});
