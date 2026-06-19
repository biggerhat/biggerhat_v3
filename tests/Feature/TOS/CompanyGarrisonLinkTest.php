<?php

use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Garrison;
use App\Models\TOS\GarrisonUnit;
use App\Models\TOS\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->allegiance = Allegiance::factory()->earth()->create();
});

it('creates a Company tied to a Garrison and snaps allegiance to match', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    // Attempt to pass a wrong allegiance — server should override with the Garrison's.
    $wrong = Allegiance::factory()->malifaux()->create();

    $this->actingAs($this->user)->post(route('tos.companies.store'), [
        'name' => 'Round 1 Vanguard',
        'allegiance_id' => $wrong->id,
        'garrison_id' => $garrison->id,
    ])->assertRedirect();

    $company = Company::where('name', 'Round 1 Vanguard')->first();
    expect($company)->not->toBeNull();
    expect($company->garrison_id)->toBe($garrison->id);
    expect($company->allegiance_id)->toBe($this->allegiance->id);
});

it('rejects hiring a Unit that is not in the Garrison pool', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    // Hire the in-pool unit as Commander so the scrip-pool gate (which
    // demands a Commander before any other hire) doesn't mask the
    // Garrison-pool gate we're actually testing.
    $commanderRule = \App\Models\TOS\SpecialUnitRule::where('slug', 'commander')->first()
        ?? \App\Models\TOS\SpecialUnitRule::factory()->create(['slug' => 'commander', 'name' => 'Commander']);
    $inPool = Unit::factory()->create(['scrip' => 18, 'name' => 'Insider']);
    $strange = Unit::factory()->create(['scrip' => 5, 'name' => 'Outsider']);
    // Both are Commander-eligible so this test isolates the Garrison-pool gate.
    $inPool->specialUnitRules()->attach($commanderRule->id);
    $strange->specialUnitRules()->attach($commanderRule->id);
    $this->allegiance->units()->attach([$inPool->id, $strange->id]);
    GarrisonUnit::create(['garrison_id' => $garrison->id, 'unit_id' => $inPool->id, 'is_commander' => true]);

    $company = Company::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create([
        'garrison_id' => $garrison->id,
    ]);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $strange->id,
        'is_commander' => true,
    ])->assertSessionHasErrors('unit_id');
    expect($company->fresh()->companyUnits)->toHaveCount(0);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $inPool->id,
        'is_commander' => true,
    ])->assertRedirect();
    expect($company->fresh()->companyUnits)->toHaveCount(1);
});

it('rejects attaching an Asset that is not in the Garrison pool', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    $inPool = Asset::factory()->create(['scrip_cost' => 3]);
    $strange = Asset::factory()->create(['scrip_cost' => 3]);
    $garrison->assets()->attach($inPool->id, ['quantity' => 2]);

    $cmdr = Unit::factory()->create(['scrip' => 18]);
    $this->allegiance->units()->attach($cmdr->id);
    GarrisonUnit::create(['garrison_id' => $garrison->id, 'unit_id' => $cmdr->id, 'is_commander' => true]);

    $company = Company::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create([
        'garrison_id' => $garrison->id,
    ]);
    $cu = CompanyUnit::create([
        'company_id' => $company->id,
        'unit_id' => $cmdr->id,
        'is_commander' => true,
    ]);

    $this->actingAs($this->user)->post(
        route('tos.companies.assets.attach', [$company->slug, $cu->id]),
        ['asset_id' => $strange->id],
    )->assertSessionHasErrors('asset_id');
    expect($cu->fresh()->assets)->toHaveCount(0);

    $this->actingAs($this->user)->post(
        route('tos.companies.assets.attach', [$company->slug, $cu->id]),
        ['asset_id' => $inPool->id],
    )->assertRedirect();
    expect($cu->fresh()->assets)->toHaveCount(1);
});

it('filters the View hireable_units payload to the Garrison pool when set', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    $inPool = Unit::factory()->create(['name' => 'Insider', 'scrip' => 5]);
    $strange = Unit::factory()->create(['name' => 'Outsider', 'scrip' => 5]);
    $this->allegiance->units()->attach([$inPool->id, $strange->id]);
    GarrisonUnit::create(['garrison_id' => $garrison->id, 'unit_id' => $inPool->id, 'is_commander' => false]);

    $company = Company::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create([
        'garrison_id' => $garrison->id,
    ]);

    $this->actingAs($this->user)->get(route('tos.companies.view', $company->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('hireable_units', 1)
            ->where('hireable_units.0.id', $inPool->id)
        );
});

it('lets the owner clear the Garrison link', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create([
        'garrison_id' => $garrison->id,
    ]);

    $this->actingAs($this->user)->post(route('tos.companies.set_garrison', $company->slug), [
        'garrison_id' => null,
    ])->assertRedirect();

    expect($company->fresh()->garrison_id)->toBeNull();
});

it('lets the owner switch to a different Garrison and snaps the allegiance', function () {
    $a = Allegiance::factory()->earth()->create();
    $b = Allegiance::factory()->malifaux()->create();
    $oldGarrison = Garrison::factory()->forUser($this->user)->forAllegiance($a)->create();
    $newGarrison = Garrison::factory()->forUser($this->user)->forAllegiance($b)->create();

    $company = Company::factory()->forUser($this->user)->forAllegiance($a)->create([
        'garrison_id' => $oldGarrison->id,
    ]);

    $this->actingAs($this->user)->post(route('tos.companies.set_garrison', $company->slug), [
        'garrison_id' => $newGarrison->id,
    ])->assertRedirect();

    $fresh = $company->fresh();
    expect($fresh->garrison_id)->toBe($newGarrison->id);
    expect($fresh->allegiance_id)->toBe($b->id);
});

it("blocks setting a Garrison the user doesn't own", function () {
    $other = User::factory()->create();
    $foreignGarrison = Garrison::factory()->forUser($other)->forAllegiance($this->allegiance)->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.companies.set_garrison', $company->slug), [
        'garrison_id' => $foreignGarrison->id,
    ])->assertNotFound();

    expect($company->fresh()->garrison_id)->toBeNull();
});

it('degrades affected Companies when the Garrison is deleted (FK nullOnDelete)', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create([
        'garrison_id' => $garrison->id,
    ]);

    $garrison->delete();

    expect($company->fresh()->garrison_id)->toBeNull();
});

it('preselects the Garrison on the create form when garrison_id is in the query', function () {
    $garrison = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->get(route('tos.companies.create', ['garrison_id' => $garrison->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preselect_garrison_id', $garrison->id)
        );
});

it('ships available_garrisons on the View page filtered to the same allegiance + user', function () {
    $earth = Allegiance::factory()->earth()->create();
    $malifaux = Allegiance::factory()->malifaux()->create();

    $matching = Garrison::factory()->forUser($this->user)->forAllegiance($earth)->create(['name' => 'Match']);
    Garrison::factory()->forUser($this->user)->forAllegiance($malifaux)->create(['name' => 'WrongType']);
    $stranger = User::factory()->create();
    Garrison::factory()->forUser($stranger)->forAllegiance($earth)->create(['name' => 'NotMine']);

    $company = Company::factory()->forUser($this->user)->forAllegiance($earth)->create();

    $this->actingAs($this->user)->get(route('tos.companies.view', $company->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('available_garrisons', 1)
            ->where('available_garrisons.0.id', $matching->id)
        );
});
