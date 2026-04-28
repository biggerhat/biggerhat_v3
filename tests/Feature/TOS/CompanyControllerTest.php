<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects guests trying to view the Companies index', function () {
    $this->get(route('tos.companies.index'))->assertRedirect(route('login'));
});

it('lists only the current user\'s Companies', function () {
    $alle = Allegiance::factory()->earth()->create();
    $mine = Company::factory()->forUser($this->user)->forAllegiance($alle)->create(['name' => 'Mine']);
    Company::factory()->forAllegiance($alle)->create(['name' => 'Theirs']); // some other user

    $this->actingAs($this->user)->get(route('tos.companies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Companies/Index')
            ->has('companies', 1)
            ->where('companies.0.id', $mine->id)
        );
});

it('creates a Company bound to the current user', function () {
    $alle = Allegiance::factory()->earth()->create();

    $this->actingAs($this->user)->post(route('tos.companies.store'), [
        'name' => 'The Iron Vanguard',
        'allegiance_id' => $alle->id,
    ])->assertRedirect();

    $company = Company::where('name', 'The Iron Vanguard')->first();
    expect($company)->not->toBeNull()
        ->and($company->user_id)->toBe($this->user->id)
        ->and($company->allegiance_id)->toBe($alle->id);
});

it('blocks viewing another user\'s Company', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forAllegiance($alle)->create();

    $this->actingAs($this->user)->get(route('tos.companies.view', $company->slug))->assertForbidden();
});

it('rejects adding a unit that is not hireable into the Company\'s Allegiance', function () {
    $earth = Allegiance::factory()->earth()->create();
    $malifaux = Allegiance::factory()->malifaux()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($earth)->create();

    // Unit attached only to a Malifaux Allegiance, no Earth restriction.
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$malifaux->id]);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors(['unit_id']);

    expect($company->companyUnits()->count())->toBe(0);
});

it('adds a hireable unit and tracks Commander promotion uniquely', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $u1 = Unit::factory()->withSides()->create();
    $u1->allegiances()->sync([$alle->id]);
    $u2 = Unit::factory()->withSides()->create();
    $u2->allegiances()->sync([$alle->id]);

    // First unit promoted to Commander.
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $u1->id,
        'is_commander' => true,
    ])->assertRedirect();

    // Second unit also promoted — should demote the first.
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $u2->id,
        'is_commander' => true,
    ])->assertRedirect();

    $company->refresh();
    $commanders = $company->companyUnits()->where('is_commander', true)->pluck('unit_id');
    expect($commanders->all())->toBe([$u2->id]);
});

it('rejects attaching an Asset that fails its Allegiance limit', function () {
    $earth = Allegiance::factory()->earth()->create();
    $other = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($earth)->create();

    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$earth->id]);
    $companyUnit = CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'is_commander' => false, 'position' => 0]);

    $asset = Asset::factory()->create();
    $asset->limits()->create([
        'limit_type' => 'restricted',
        'parameter_type' => 'allegiance',
        'parameter_allegiance_id' => $other->id,
    ]);

    $this->actingAs($this->user)->post(route('tos.companies.assets.attach', [$company->slug, $companyUnit->id]), [
        'asset_id' => $asset->id,
    ])->assertSessionHasErrors(['asset_id']);

    expect($companyUnit->assets()->count())->toBe(0);
});

it('rejects hiring a non-Commander unit that would exceed the Commander Scrip budget', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $cmdr = Unit::factory()->withSides()->create(['scrip' => 5]);
    $cmdr->allegiances()->sync([$alle->id]);
    $expensive = Unit::factory()->withSides()->create(['scrip' => 9]);
    $expensive->allegiances()->sync([$alle->id]);

    // Commander provides the budget — fine.
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $cmdr->id,
        'is_commander' => true,
    ])->assertRedirect();

    // Hiring the expensive unit (9) into a 5-scrip budget should reject.
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $expensive->id,
    ])->assertSessionHasErrors(['unit_id']);

    expect($company->companyUnits()->where('is_commander', false)->count())->toBe(0);
});

it('auto-attaches a Combined Arms child when its parent is hired', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $child = Unit::factory()->withSides()->create(['name' => 'Komainu', 'scrip' => 0]);
    $child->allegiances()->sync([$alle->id]);
    $parent = Unit::factory()->withSides()->create(['name' => 'Lien', 'scrip' => 8, 'combined_arms_child_id' => $child->id]);
    $parent->allegiances()->sync([$alle->id]);

    // First hire a Commander to give us a budget.
    $cmdr = Unit::factory()->withSides()->create(['scrip' => 12]);
    $cmdr->allegiances()->sync([$alle->id]);
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $cmdr->id, 'is_commander' => true]);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $parent->id,
    ])->assertRedirect();

    $childRow = $company->companyUnits()->where('unit_id', $child->id)->first();
    expect($childRow)->not->toBeNull()
        ->and($childRow->is_combined_arms_child)->toBeTrue();
});

it('blocks removing a Combined Arms child directly', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $child = Unit::factory()->withSides()->create();
    $child->allegiances()->sync([$alle->id]);
    $childRow = CompanyUnit::create([
        'company_id' => $company->id,
        'unit_id' => $child->id,
        'is_commander' => false,
        'is_combined_arms_child' => true,
        'position' => 0,
    ]);

    $this->actingAs($this->user)->post(route('tos.companies.units.remove', [$company->slug, $childRow->id]))
        ->assertSessionHasErrors(['unit_id']);

    expect(CompanyUnit::find($childRow->id))->not->toBeNull();
});

it('removing a Combined Arms parent also removes the auto-attached child', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $child = Unit::factory()->withSides()->create(['scrip' => 0]);
    $child->allegiances()->sync([$alle->id]);
    $parent = Unit::factory()->withSides()->create(['scrip' => 0, 'combined_arms_child_id' => $child->id]);
    $parent->allegiances()->sync([$alle->id]);

    $cmdr = Unit::factory()->withSides()->create(['scrip' => 10]);
    $cmdr->allegiances()->sync([$alle->id]);
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $cmdr->id, 'is_commander' => true]);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $parent->id]);
    $parentRow = $company->companyUnits()->where('unit_id', $parent->id)->first();

    $this->actingAs($this->user)->post(route('tos.companies.units.remove', [$company->slug, $parentRow->id]))
        ->assertRedirect();

    expect($company->companyUnits()->count())->toBe(1); // only the Commander left
});

it('blocks attaching the same Unique Asset to a second company_unit in the Company', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $u1 = Unit::factory()->withSides()->create();
    $u1->allegiances()->sync([$alle->id]);
    $u2 = Unit::factory()->withSides()->create();
    $u2->allegiances()->sync([$alle->id]);

    $cu1 = CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $u1->id, 'is_commander' => true, 'is_combined_arms_child' => false, 'position' => 0]);
    $cu2 = CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $u2->id, 'is_commander' => false, 'is_combined_arms_child' => false, 'position' => 1]);

    $asset = Asset::factory()->create(['scrip_cost' => 0]);
    $asset->limits()->create(['limit_type' => 'unique']);
    $cu1->assets()->attach($asset->id);

    // u1 was loaded with scrip > 0 by factory, but Commander's scrip provides
    // budget so scripBudget covers any incidental cost.
    $u2->scrip = 0;
    $u2->save();

    $this->actingAs($this->user)->post(route('tos.companies.assets.attach', [$company->slug, $cu2->id]), [
        'asset_id' => $asset->id,
    ])->assertSessionHasErrors(['asset_id']);

    expect($cu2->assets()->count())->toBe(0);
});

it('blocks attaching a second Asset that would occupy the same Slot location on the same unit', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $unit = Unit::factory()->withSides()->create(['scrip' => 0]);
    $unit->allegiances()->sync([$alle->id]);
    $cmdr = Unit::factory()->withSides()->create(['scrip' => 10]);
    $cmdr->allegiances()->sync([$alle->id]);

    CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $cmdr->id, 'is_commander' => true, 'is_combined_arms_child' => false, 'position' => 0]);
    $cu = CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'is_commander' => false, 'is_combined_arms_child' => false, 'position' => 1]);

    $assetA = Asset::factory()->create(['name' => 'Right Arm A', 'scrip_cost' => 0]);
    $assetA->limits()->create(['limit_type' => 'slot', 'parameter_type' => 'location', 'parameter_value' => 'Arm']);
    $assetB = Asset::factory()->create(['name' => 'Right Arm B', 'scrip_cost' => 0]);
    $assetB->limits()->create(['limit_type' => 'slot', 'parameter_type' => 'location', 'parameter_value' => 'Arm']);

    $cu->assets()->attach($assetA->id);

    $this->actingAs($this->user)->post(route('tos.companies.assets.attach', [$company->slug, $cu->id]), [
        'asset_id' => $assetB->id,
    ])->assertSessionHasErrors(['asset_id']);

    expect($cu->assets()->count())->toBe(1);
});

it('Neutral units can be hired into matching-type Allegiances', function () {
    $earth1 = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($earth1)->create();

    // Neutral units cost Scrip like any other hire; promote a Commander
    // first to seed the budget.
    $cmdr = Unit::factory()->withSides()->create(['scrip' => 10]);
    $cmdr->allegiances()->sync([$earth1->id]);
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $cmdr->id,
        'is_commander' => true,
    ])->assertRedirect();

    // Neutral Earth unit — no allegiance attachments, restriction set.
    $neutral = Unit::factory()->neutralFor(AllegianceTypeEnum::Earth)->withSides()->create(['scrip' => 4]);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $neutral->id,
    ])->assertRedirect();

    expect($company->companyUnits()->count())->toBe(2);
});
