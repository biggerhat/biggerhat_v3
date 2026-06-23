<?php

use App\Models\TOS\Allegiance;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;

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

it('renders the Earth-side type page pooling units across all Earth allegiances + Neutral Earth', function () {
    $ke = \App\Models\TOS\Allegiance::factory()->earth()->create(['slug' => 'kings_empire', 'name' => "King's Empire"]);
    $aby = \App\Models\TOS\Allegiance::factory()->earth()->create(['slug' => 'abyssinia', 'name' => 'Abyssinia']);
    $cult = \App\Models\TOS\Allegiance::factory()->malifaux()->create(['slug' => 'cult_of_the_burning_man', 'name' => 'Cult']);

    $u1 = \App\Models\TOS\Unit::factory()->withSides()->create(['name' => 'KE Unit']);
    $u1->allegiances()->sync([$ke->id]);
    $u2 = \App\Models\TOS\Unit::factory()->withSides()->create(['name' => 'Aby Unit']);
    $u2->allegiances()->sync([$aby->id]);
    $u3 = \App\Models\TOS\Unit::factory()->withSides()->neutralFor(\App\Enums\TOS\AllegianceTypeEnum::Earth)->create(['name' => 'Neutral Earth']);
    $u4 = \App\Models\TOS\Unit::factory()->withSides()->create(['name' => 'Cult Unit']);
    $u4->allegiances()->sync([$cult->id]);

    $this->get(route('tos.allegiances.viewByType', 'earth'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Allegiances/View')
            ->where('allegiance.slug', 'type-earth')
            ->where('allegiance.type', 'earth')
            ->has('units', 3)
        );
});

it('returns 404 for an invalid type slug', function () {
    $this->get(route('tos.allegiances.viewByType', 'made-up'))->assertNotFound();
});

it('averages roster scrip excluding Commanders and does not count them as Champions', function () {
    $a = Allegiance::factory()->create(['slug' => 'guild', 'name' => 'Guild']);
    $commander = SpecialUnitRule::factory()->create(['slug' => 'commander', 'name' => 'Commander']);
    $champion = SpecialUnitRule::factory()->create(['slug' => 'champion', 'name' => 'Champion']);

    // Rank-and-file: 3, 3, 4, 6 → average 4. The scrip-6 one is a (non-commander) Champion.
    foreach ([3, 3, 4] as $scrip) {
        Unit::factory()->withSides()->create(['scrip' => $scrip])->allegiances()->sync([$a->id]);
    }
    $champUnit = Unit::factory()->withSides()->create(['scrip' => 6]);
    $champUnit->allegiances()->sync([$a->id]);
    $champUnit->specialUnitRules()->sync([$champion->id]);

    // Commander (also a Champion by the rules), outlier scrip 18.
    $cmdr = Unit::factory()->withSides()->create(['scrip' => 18]);
    $cmdr->allegiances()->sync([$a->id]);
    $cmdr->specialUnitRules()->sync([$commander->id, $champion->id]);

    $this->get(route('tos.allegiances.view', $a->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statistics.total', 5)
            ->where('statistics.average_scrip', fn ($v) => (float) $v === 4.0)
            ->where('statistics.by_rule', function ($rules) {
                $rules = collect($rules);

                return $rules->firstWhere('slug', 'commander')['count'] === 1
                    && $rules->firstWhere('slug', 'champion')['count'] === 1; // commander excluded
            })
        );
});
