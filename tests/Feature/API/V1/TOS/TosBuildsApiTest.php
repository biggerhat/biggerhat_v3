<?php

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Garrison;
use App\Models\TOS\GarrisonUnit;
use App\Models\TOS\Unit;

// ============================================================
// Companies (the TOS analog of Malifaux crew builds)
// ============================================================

it('lists only public companies in the standard paginated envelope', function () {
    Company::factory()->create(['is_public' => true]);
    Company::factory()->create(['is_public' => false]);

    $resp = $this->getJson('/api/v1/tos/companies');

    // Build endpoints mirror Malifaux CrewBuild: the flat paginator shape.
    $resp->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'per_page', 'total'])
        ->assertJsonCount(1, 'data');
});

it('returns a public company roster by share code', function () {
    $unit = Unit::factory()->create(['name' => 'Royal Rifle Corps', 'scrip' => 8]);
    $company = Company::factory()->create(['is_public' => true, 'name' => "King's Hand"]);
    CompanyUnit::create([
        'company_id' => $company->id,
        'unit_id' => $unit->id,
        'position' => 1,
        'is_commander' => true,
    ]);

    $resp = $this->getJson("/api/v1/tos/companies/{$company->share_code}");

    $resp->assertOk()
        ->assertJsonPath('name', "King's Hand")
        ->assertJsonPath('has_commander', true)
        ->assertJsonPath('scrip_budget', 8)
        ->assertJsonPath('units.0.name', 'Royal Rifle Corps')
        ->assertJsonPath('units.0.is_commander', true);
});

it('404s a non-public company share code', function () {
    $company = Company::factory()->create(['is_public' => false]);

    $this->getJson("/api/v1/tos/companies/{$company->share_code}")->assertNotFound();
});

it('filters companies by allegiance slug', function () {
    $a = \App\Models\TOS\Allegiance::factory()->create(['slug' => 'kings_empire']);
    Company::factory()->forAllegiance($a)->create(['is_public' => true]);
    Company::factory()->create(['is_public' => true]);

    $resp = $this->getJson('/api/v1/tos/companies?allegiance=kings_empire');
    expect($resp->json('data'))->toHaveCount(1);
});

// ============================================================
// Garrisons (tournament pool)
// ============================================================

it('lists only public garrisons and exposes format + scrip budget', function () {
    Garrison::factory()->create(['is_public' => true, 'format' => GarrisonFormatEnum::OneCommander]);
    Garrison::factory()->create(['is_public' => false]);

    $resp = $this->getJson('/api/v1/tos/garrisons');

    $resp->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'per_page', 'total'])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.format', GarrisonFormatEnum::OneCommander->value);
});

it('returns a public garrison pool by share code', function () {
    $unit = Unit::factory()->create(['name' => 'Pierce Drill', 'scrip' => 6]);
    $garrison = Garrison::factory()->create(['is_public' => true, 'name' => 'Round 1 Pool']);
    GarrisonUnit::create([
        'garrison_id' => $garrison->id,
        'unit_id' => $unit->id,
        'position' => 1,
        'is_commander' => false,
    ]);

    $resp = $this->getJson("/api/v1/tos/garrisons/{$garrison->share_code}");

    $resp->assertOk()
        ->assertJsonPath('name', 'Round 1 Pool')
        ->assertJsonPath('units.0.name', 'Pierce Drill')
        ->assertJsonStructure(['format', 'scrip_budget', 'scrip_spent', 'scrip_remaining', 'is_legal', 'assets', 'stratagems', 'envoys']);
});

it('404s a non-public garrison share code', function () {
    $garrison = Garrison::factory()->create(['is_public' => false]);

    $this->getJson("/api/v1/tos/garrisons/{$garrison->share_code}")->assertNotFound();
});
