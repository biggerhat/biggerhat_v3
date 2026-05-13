<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;

it('renders the advanced search page with empty results when no filters set', function () {
    $this->get(route('tos.search'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Search/Index')
            ->where('result_count', 0)
            ->where('result_breakdown.units', 0)
            ->where('result_breakdown.assets', 0)
            ->where('result_breakdown.stratagems', 0)
            ->has('allegiances')
            ->has('special_rules')
            ->has('action_types')
            ->has('usage_limits')
            ->has('sort_options', 3)
        );
});

it('filters units by name across name/title/description', function () {
    $u1 = Unit::factory()->withSides()->create(['name' => 'Earl Burns', 'title' => 'Royal Engineer', 'description' => 'A grizzled ironclad veteran.']);
    UnitSculpt::factory()->forUnit($u1)->create();
    $u2 = Unit::factory()->withSides()->create(['name' => 'Mustelid Pack', 'title' => null, 'description' => null]);
    UnitSculpt::factory()->forUnit($u2)->create();

    // Title hit: 'Engineer' is in u1.title only.
    $this->get(route('tos.search', ['name' => 'Engineer']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('results.data.0.name', 'Earl Burns')
        );
});

it('filters by allegiance with AND logic across multiple slugs', function () {
    $a1 = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);
    $a2 = Allegiance::factory()->malifaux()->syndicate()->create(['slug' => 'court_of_two']);

    $multi = Unit::factory()->withSides()->create(['name' => 'Dual']);
    $multi->allegiances()->sync([$a1->id, $a2->id]);
    UnitSculpt::factory()->forUnit($multi)->create();

    $solo = Unit::factory()->withSides()->create(['name' => 'Solo']);
    $solo->allegiances()->sync([$a1->id]);
    UnitSculpt::factory()->forUnit($solo)->create();

    $this->get(route('tos.search', ['allegiance' => 'kings_empire,court_of_two', 'allegiance_logic' => 'and']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('results.data.0.name', 'Dual')
        );
});

it('respects scrip min/max range', function () {
    $cheap = Unit::factory()->withSides()->create(['name' => 'Cheap', 'scrip' => 3]);
    $mid = Unit::factory()->withSides()->create(['name' => 'Mid', 'scrip' => 6]);
    $pricey = Unit::factory()->withSides()->create(['name' => 'Pricey', 'scrip' => 12]);
    foreach ([$cheap, $mid, $pricey] as $u) {
        UnitSculpt::factory()->forUnit($u)->create();
    }

    $this->get(route('tos.search', ['scrip_min' => 5, 'scrip_max' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('results.data.0.name', 'Mid')
        );
});

it('filters by side stat with side=standard scope', function () {
    $u = Unit::factory()->withSides()->create(['name' => 'Tank']);
    UnitSculpt::factory()->forUnit($u)->create();
    $u->sides()->where('side', 'standard')->update(['armor' => 4]);
    $u->sides()->where('side', 'glory')->update(['armor' => 1]);

    $other = Unit::factory()->withSides()->create(['name' => 'Squishy']);
    UnitSculpt::factory()->forUnit($other)->create();
    $other->sides()->update(['armor' => 0]);

    $this->get(route('tos.search', ['side' => 'standard', 'armor_min' => 3]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('results.data.0.name', 'Tank')
        );
});

it('filters by special rule slug', function () {
    SpecialUnitRule::query()->updateOrCreate(['slug' => 'commander'], ['name' => 'Commander']);
    SpecialUnitRule::query()->updateOrCreate(['slug' => 'titan'], ['name' => 'Titan']);

    $cmdr = Unit::factory()->commander()->withSides()->create(['name' => 'CmdrUnit']);
    UnitSculpt::factory()->forUnit($cmdr)->create();
    $rookie = Unit::factory()->withSides()->create(['name' => 'Rookie']);
    UnitSculpt::factory()->forUnit($rookie)->create();

    $this->get(route('tos.search', ['special_rule' => 'commander']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('results.data.0.name', 'CmdrUnit')
        );
});

it('filters by glory_tactics matching either the override or the fallback to standard tactics', function () {
    $diff = Unit::factory()->withSides()->create(['name' => 'Flips', 'tactics' => '2', 'glory_tactics' => '3']);
    UnitSculpt::factory()->forUnit($diff)->create();
    $same = Unit::factory()->withSides()->create(['name' => 'Steady', 'tactics' => '3', 'glory_tactics' => null]);
    UnitSculpt::factory()->forUnit($same)->create();
    $other = Unit::factory()->withSides()->create(['name' => 'Different', 'tactics' => '1', 'glory_tactics' => null]);
    UnitSculpt::factory()->forUnit($other)->create();

    // Searching glory=3 should hit the override AND the fallback row.
    $this->get(route('tos.search', ['glory_tactics' => '3']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('result_breakdown.units', 2));
});

it('respects has=neutral for restriction-bearing units', function () {
    $neutral = Unit::factory()->withSides()->neutralFor(AllegianceTypeEnum::Earth)->create(['name' => 'NeutralEarth']);
    UnitSculpt::factory()->forUnit($neutral)->create();
    $standard = Unit::factory()->withSides()->create(['name' => 'Standard']);
    UnitSculpt::factory()->forUnit($standard)->create();

    $this->get(route('tos.search', ['has' => 'neutral']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('results.data.0.name', 'NeutralEarth')
        );
});

it('rolls Asset rows into the result list when name/scrip filters are present', function () {
    $unit = Unit::factory()->withSides()->create(['name' => 'Spyglass Crew']);
    UnitSculpt::factory()->forUnit($unit)->create();
    Asset::factory()->create(['name' => 'Spyglass', 'scrip_cost' => 2]);

    $this->get(route('tos.search', ['name' => 'Spyglass']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.units', 1)
            ->where('result_breakdown.assets', 1)
        );
});

it('CSV export streams a csv with the expected header row', function () {
    $u = Unit::factory()->withSides()->create(['name' => 'CsvBait']);
    UnitSculpt::factory()->forUnit($u)->create();

    $response = $this->get(route('tos.search.export', ['name' => 'CsvBait']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $body = $response->streamedContent();
    expect($body)->toContain('Name,Title,Allegiances')
        ->and($body)->toContain('CsvBait');
});

it('saves a search for the authenticated user with game_system=tos', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tos.search.save'), [
        'name' => 'My Titans',
        'query_params' => ['special_rule' => 'titan'],
    ])->assertRedirect();

    $saved = \App\Models\SavedSearch::where('user_id', $user->id)->first();
    expect($saved)->not->toBeNull()
        ->and($saved->game_system)->toBe('tos')
        ->and($saved->name)->toBe('My Titans')
        ->and($saved->query_params)->toBe(['special_rule' => 'titan']);
});

it('blocks deleting another user\'s saved search', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $saved = \App\Models\SavedSearch::create([
        'user_id' => $other->id,
        'game_system' => 'tos',
        'name' => 'Other',
        'query_params' => [],
    ]);

    $this->actingAs($user)->post(route('tos.search.saved.delete', $saved->id))->assertForbidden();
    expect(\App\Models\SavedSearch::find($saved->id))->not->toBeNull();
});

// ─── Side-record filter parity (Asset / Stratagem / AllegianceCard) ───
// Same intent as the Malifaux upgrade filter fix — when the user constrains
// the search by allegiance, side-records should respect that constraint
// instead of leaking the entire table back into the results.

it('respects allegiance filter on assets', function () {
    $kingsEmpire = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);
    $cult = Allegiance::factory()->malifaux()->create(['slug' => 'cult']);

    $matching = Asset::factory()->create(['name' => 'Allied Banner']);
    $matching->allegiances()->sync([$kingsEmpire->id]);
    $mismatching = Asset::factory()->create(['name' => 'Allied Charm']);
    $mismatching->allegiances()->sync([$cult->id]);

    $response = $this->get(route('tos.search', [
        'name' => 'Allied',
        'allegiance' => 'kings_empire',
    ]));

    $response->assertOk();
    $assetNames = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'asset')
        ->pluck('name')
        ->all();

    expect($assetNames)->toBe(['Allied Banner']);
});

it('respects allegiance_exclude on stratagems', function () {
    $kingsEmpire = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);
    $cult = Allegiance::factory()->malifaux()->create(['slug' => 'cult']);

    Stratagem::factory()->create([
        'name' => 'Bombard Excl A',
        'allegiance_id' => $kingsEmpire->id,
        'allegiance_type' => AllegianceTypeEnum::Earth,
    ]);
    Stratagem::factory()->create([
        'name' => 'Bombard Excl B',
        'allegiance_id' => $cult->id,
        'allegiance_type' => AllegianceTypeEnum::Malifaux,
    ]);

    $response = $this->get(route('tos.search', [
        'name' => 'Bombard',
        'allegiance_exclude' => 'kings_empire',
    ]));

    $response->assertOk();
    $names = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'stratagem')
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Bombard Excl B']);
});

it('respects allegiance filter on allegiance cards', function () {
    $kingsEmpire = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);
    $cult = Allegiance::factory()->malifaux()->create(['slug' => 'cult']);

    AllegianceCard::factory()->create([
        'name' => 'Card Match',
        'allegiance_id' => $kingsEmpire->id,
        'body' => 'A leadership card.',
    ]);
    AllegianceCard::factory()->create([
        'name' => 'Card NoMatch',
        'allegiance_id' => $cult->id,
        'body' => 'Another leadership card.',
    ]);

    $response = $this->get(route('tos.search', [
        'name' => 'Card',
        'allegiance' => 'kings_empire',
    ]));

    $response->assertOk();
    $names = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'allegiance_card')
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Card Match']);
});

it('honours restriction (Earth/Malifaux) on stratagems', function () {
    $earth = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);
    $malifaux = Allegiance::factory()->malifaux()->create(['slug' => 'cult']);

    Stratagem::factory()->create([
        'name' => 'Restrict Earth',
        'allegiance_id' => $earth->id,
        'allegiance_type' => AllegianceTypeEnum::Earth,
    ]);
    Stratagem::factory()->create([
        'name' => 'Restrict Mali',
        'allegiance_id' => $malifaux->id,
        'allegiance_type' => AllegianceTypeEnum::Malifaux,
    ]);

    $response = $this->get(route('tos.search', [
        'name' => 'Restrict',
        'restriction' => AllegianceTypeEnum::Earth->value,
    ]));

    $names = collect($response->viewData('page')['props']['results']['data'])
        ->where('result_type', 'stratagem')
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Restrict Earth']);
});

it('short-circuits side-record queries when units_only=1', function () {
    $kingsEmpire = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);

    $unit = Unit::factory()->withSides()->create(['name' => 'Shared Term Unit']);
    UnitSculpt::factory()->forUnit($unit)->create();

    $asset = Asset::factory()->create(['name' => 'Shared Term Asset']);
    $asset->allegiances()->sync([$kingsEmpire->id]);
    Stratagem::factory()->create([
        'name' => 'Shared Term Strat',
        'allegiance_id' => $kingsEmpire->id,
        'allegiance_type' => AllegianceTypeEnum::Earth,
    ]);
    AllegianceCard::factory()->create([
        'name' => 'Shared Term Card',
        'allegiance_id' => $kingsEmpire->id,
        'body' => 'something',
    ]);

    $this->get(route('tos.search', [
        'name' => 'Shared Term',
        'units_only' => '1',
    ]))->assertInertia(fn ($page) => $page
        ->where('result_breakdown.assets', 0)
        ->where('result_breakdown.stratagems', 0)
        ->where('result_breakdown.allegiance_cards', 0)
        ->where('result_breakdown.units', 1)
    );

    // And without the toggle, the side-records come back.
    $this->get(route('tos.search', ['name' => 'Shared Term']))
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.assets', 1)
            ->where('result_breakdown.stratagems', 1)
            ->where('result_breakdown.allegiance_cards', 1)
        );
});

it('does not pull side-records on a bare allegiance browse', function () {
    // Preserves the existing gate — only text/cost filters trigger
    // side-record queries. A bare allegiance filter is a Unit-shape browse.
    $kingsEmpire = Allegiance::factory()->earth()->create(['slug' => 'kings_empire']);

    $asset = Asset::factory()->create();
    $asset->allegiances()->sync([$kingsEmpire->id]);
    Stratagem::factory()->create([
        'allegiance_id' => $kingsEmpire->id,
        'allegiance_type' => AllegianceTypeEnum::Earth,
    ]);
    AllegianceCard::factory()->create([
        'allegiance_id' => $kingsEmpire->id,
    ]);

    $this->get(route('tos.search', ['allegiance' => 'kings_empire']))
        ->assertInertia(fn ($page) => $page
            ->where('result_breakdown.assets', 0)
            ->where('result_breakdown.stratagems', 0)
            ->where('result_breakdown.allegiance_cards', 0)
        );
});
