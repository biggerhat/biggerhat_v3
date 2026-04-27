<?php

use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;

it('renders the compare page with no selection', function () {
    $this->get(route('tos.compare'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Compare/Index')
            ->has('units', 0)
            ->where('selected_slugs', [])
            ->where('max_units', 4)
        );
});

it('loads units from a comma-separated sculpt slug list', function () {
    $u1 = Unit::factory()->withSides()->create(['name' => 'Alpha']);
    $u2 = Unit::factory()->withSides()->create(['name' => 'Beta']);
    $s1 = UnitSculpt::factory()->forUnit($u1)->create();
    $s2 = UnitSculpt::factory()->forUnit($u2)->create();

    $this->get(route('tos.compare', ['units' => $s1->slug.','.$s2->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Compare/Index')
            ->has('units', 2)
            ->where('selected_slugs.0', $s1->slug)
            ->where('selected_slugs.1', $s2->slug)
        );
});

it('caps the selection at max_units', function () {
    $sculpts = collect(range(1, 6))->map(function ($i) {
        $u = Unit::factory()->withSides()->create(['name' => "Unit {$i}"]);

        return UnitSculpt::factory()->forUnit($u)->create();
    });

    $slugs = $sculpts->pluck('slug')->implode(',');

    $this->get(route('tos.compare', ['units' => $slugs]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Compare/Index')
            ->has('units', 4)
        );
});

it('preserves URL order even when DB returns rows in a different order', function () {
    $u1 = Unit::factory()->withSides()->create(['name' => 'Z-Alpha']);
    $u2 = Unit::factory()->withSides()->create(['name' => 'A-Beta']);
    $s1 = UnitSculpt::factory()->forUnit($u1)->create();
    $s2 = UnitSculpt::factory()->forUnit($u2)->create();

    // URL order: u1, u2 — even though u2's name sorts first alphabetically.
    $this->get(route('tos.compare', ['units' => $s1->slug.','.$s2->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('units.0.name', 'Z-Alpha')
            ->where('units.1.name', 'A-Beta')
        );
});

it('preserves URL order when a unit is referenced by its second sculpt', function () {
    $u1 = Unit::factory()->withSides()->create(['name' => 'Alpha']);
    $u2 = Unit::factory()->withSides()->create(['name' => 'Beta']);
    // Two sculpts each. URL references u1's second sculpt and u2's first.
    UnitSculpt::factory()->forUnit($u1)->create(['sort_order' => 0]);
    $alt1 = UnitSculpt::factory()->forUnit($u1)->create(['sort_order' => 1]);
    $first2 = UnitSculpt::factory()->forUnit($u2)->create(['sort_order' => 0]);

    $this->get(route('tos.compare', ['units' => $alt1->slug.','.$first2->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('units', 2)
            ->where('units.0.name', 'Alpha')
            ->where('units.1.name', 'Beta')
        );
});
