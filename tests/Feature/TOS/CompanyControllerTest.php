<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Company;
use App\Models\TOS\CompanyUnit;
use App\Models\TOS\SpecialUnitRule;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/** A Commander-eligible unit: carries the Commander rule and the given Allegiance. */
function commanderUnit(Allegiance $allegiance, array $attrs = []): Unit
{
    $rule = SpecialUnitRule::where('slug', 'commander')->first()
        ?? SpecialUnitRule::factory()->create(['slug' => 'commander', 'name' => 'Commander']);

    $unit = Unit::factory()->withSides()->create($attrs);
    $unit->allegiances()->sync([$allegiance->id]);
    $unit->specialUnitRules()->syncWithoutDetaching([$rule->id]);

    return $unit;
}

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

it('creates a Company with a play format and an Envoy', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoy = Allegiance::factory()->earth()->create();

    $this->actingAs($this->user)->post(route('tos.companies.store'), [
        'name' => 'Formatted',
        'allegiance_id' => $primary->id,
        'format' => GarrisonFormatEnum::TwoCommanders->value,
        'envoy_allegiance_id' => $envoy->id,
    ])->assertRedirect();

    $company = Company::where('name', 'Formatted')->first();
    expect($company->format)->toBe(GarrisonFormatEnum::TwoCommanders)
        ->and($company->envoy_allegiance_id)->toBe($envoy->id);
});

it('rejects an Envoy equal to the Primary Allegiance', function () {
    $primary = Allegiance::factory()->earth()->create();

    $this->actingAs($this->user)->post(route('tos.companies.store'), [
        'name' => 'Bad Envoy',
        'allegiance_id' => $primary->id,
        'envoy_allegiance_id' => $primary->id,
    ])->assertSessionHasErrors('envoy_allegiance_id');
});

it('builds a Stratagem deck from Primary and Envoy and rejects foreign Stratagems', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoy = Allegiance::factory()->earth()->create();
    $foreignAlle = Allegiance::factory()->malifaux()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($primary)->create(['envoy_allegiance_id' => $envoy->id]);

    $primaryStrat = \App\Models\TOS\Stratagem::factory()->create(['allegiance_id' => $primary->id]);
    $envoyStrat = \App\Models\TOS\Stratagem::factory()->create(['allegiance_id' => $envoy->id]);
    $foreign = \App\Models\TOS\Stratagem::factory()->create(['allegiance_id' => $foreignAlle->id]);

    $this->actingAs($this->user)->post(route('tos.companies.stratagems.add', $company->slug), ['stratagem_id' => $primaryStrat->id])->assertRedirect();
    expect($company->stratagems()->count())->toBe(1);

    $this->actingAs($this->user)->post(route('tos.companies.stratagems.add', $company->slug), ['stratagem_id' => $foreign->id])
        ->assertSessionHasErrors('stratagem_id');

    $this->actingAs($this->user)->post(route('tos.companies.stratagems.add', $company->slug), ['stratagem_id' => $envoyStrat->id])->assertRedirect();
    expect($company->stratagems()->count())->toBe(2);

    // Remove one.
    $this->actingAs($this->user)->post(route('tos.companies.stratagems.remove', [$company->slug, $envoyStrat->slug]))->assertRedirect();
    expect($company->stratagems()->count())->toBe(1);
});

it('caps Envoy Stratagems at two', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoy = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($primary)->create(['envoy_allegiance_id' => $envoy->id]);

    $envoyStrats = \App\Models\TOS\Stratagem::factory()->count(3)->create(['allegiance_id' => $envoy->id]);

    foreach ($envoyStrats->take(2) as $s) {
        $this->actingAs($this->user)->post(route('tos.companies.stratagems.add', $company->slug), ['stratagem_id' => $s->id])->assertRedirect();
    }
    // Third Envoy Stratagem exceeds the cap of two.
    $this->actingAs($this->user)->post(route('tos.companies.stratagems.add', $company->slug), ['stratagem_id' => $envoyStrats[2]->id])
        ->assertSessionHasErrors('stratagem_id');
    expect($company->stratagems()->count())->toBe(2);
});

it('caps Envoy-sourced hires at 50% of total Scrip', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoy = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($primary)->create(['envoy_allegiance_id' => $envoy->id]);

    // Budget = 20 (Commander Scrip), so the Envoy cap is 10.
    $cmdr = commanderUnit($primary, ['scrip' => 20]);
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $cmdr->id, 'is_commander' => true])->assertRedirect();

    $squadRule = SpecialUnitRule::where('slug', 'squad')->first() ?? SpecialUnitRule::factory()->create(['slug' => 'squad', 'name' => 'Squad']);
    $envoyUnit = Unit::factory()->withSides()->create(['scrip' => 12]);
    $envoyUnit->allegiances()->sync([$envoy->id]);
    $envoyUnit->specialUnitRules()->attach($squadRule->id);

    // 12 fits the overall budget (20) but exceeds the Envoy 50% cap (10).
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $envoyUnit->id])
        ->assertSessionHasErrors('unit_id');
    expect($company->companyUnits()->where('is_commander', false)->count())->toBe(0);
});

it('exposes Primary and Envoy Allegiance cards on the view', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoy = Allegiance::factory()->earth()->create();
    \App\Models\TOS\AllegianceCard::factory()->create(['allegiance_id' => $primary->id, 'name' => 'Primary Card']);
    \App\Models\TOS\AllegianceCard::factory()->create(['allegiance_id' => $envoy->id, 'name' => 'Envoy Card']);
    $company = Company::factory()->forUser($this->user)->forAllegiance($primary)->create(['envoy_allegiance_id' => $envoy->id]);

    $this->actingAs($this->user)->get(route('tos.companies.view', $company->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('company.allegiance.allegiance_cards.0.name', 'Primary Card')
            ->where('company.envoy_allegiance.allegiance_cards.0.name', 'Envoy Card')
            ->etc()
        );
});

it('adds Envoy Squad units to the hire pool but not non-Squad Envoy units', function () {
    $primary = Allegiance::factory()->earth()->create();
    $envoyAlleg = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($primary)->create(['envoy_allegiance_id' => $envoyAlleg->id]);

    $squadRule = \App\Models\TOS\SpecialUnitRule::factory()->create(['slug' => 'squad']);

    $primaryUnit = Unit::factory()->withSides()->create(['name' => 'Primary Trooper']);
    $primaryUnit->allegiances()->sync([$primary->id]);

    $envoySquad = Unit::factory()->withSides()->create(['name' => 'Envoy Squad']);
    $envoySquad->allegiances()->sync([$envoyAlleg->id]);
    $envoySquad->specialUnitRules()->attach($squadRule->id);

    $envoyElite = Unit::factory()->withSides()->create(['name' => 'Envoy Elite']);
    $envoyElite->allegiances()->sync([$envoyAlleg->id]); // no Squad rule → not hireable from Envoy

    $this->actingAs($this->user)
        ->get(route('tos.companies.view', $company->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hireable_units', function ($units) use ($primaryUnit, $envoySquad, $envoyElite) {
            $u = collect($units);
            $p = $u->firstWhere('id', $primaryUnit->id);
            $sq = $u->firstWhere('id', $envoySquad->id);

            return $p && $p['hire_category'] === 'direct'
                && $sq && $sq['hire_category'] === 'envoy'
                && $u->firstWhere('id', $envoyElite->id) === null;
        }));
});

it('blocks viewing another user\'s Company', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forAllegiance($alle)->create();

    $this->actingAs($this->user)->get(route('tos.companies.view', $company->slug))->assertForbidden();
});

it('views a Company restricted to a Garrison pool without error', function () {
    // Regression: the garrison hire-pool lookup ran DISTINCT + ORDER BY position,
    // which MySQL rejects. The view must render for a garrison-bound Company.
    $alle = Allegiance::factory()->earth()->create();
    $garrison = \App\Models\TOS\Garrison::factory()->forAllegiance($alle)->create();
    $unit = Unit::factory()->create();
    // Declare the same unit twice so DISTINCT actually has to collapse rows.
    \App\Models\TOS\GarrisonUnit::create(['garrison_id' => $garrison->id, 'unit_id' => $unit->id, 'position' => 1, 'is_commander' => false]);
    \App\Models\TOS\GarrisonUnit::create(['garrison_id' => $garrison->id, 'unit_id' => $unit->id, 'position' => 2, 'is_commander' => false]);

    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create(['garrison_id' => $garrison->id]);

    $this->actingAs($this->user)->get(route('tos.companies.view', $company->slug))->assertOk();
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

    $u1 = commanderUnit($alle);
    $u2 = commanderUnit($alle);

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

it('rejects promoting a unit that lacks the Commander rule', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$alle->id]);

    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), [
        'unit_id' => $unit->id,
        'is_commander' => true,
    ])->assertSessionHasErrors('unit_id');

    expect($company->companyUnits()->count())->toBe(0);
});

it('allows two Commanders under a Two-Commander format and caps a third', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create(['format' => GarrisonFormatEnum::TwoCommanders]);
    $c1 = commanderUnit($alle, ['scrip' => 10]);
    $c2 = commanderUnit($alle, ['scrip' => 12]);
    $c3 = commanderUnit($alle, ['scrip' => 8]);

    foreach ([$c1, $c2] as $c) {
        $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $c->id, 'is_commander' => true])->assertRedirect();
    }
    expect($company->companyUnits()->where('is_commander', true)->count())->toBe(2);

    // Third Commander exceeds the cap.
    $this->actingAs($this->user)->post(route('tos.companies.units.add', $company->slug), ['unit_id' => $c3->id, 'is_commander' => true])
        ->assertSessionHasErrors('unit_id');
    expect($company->companyUnits()->where('is_commander', true)->count())->toBe(2);
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

it('exposes per-unit attachability so the picker can filter to attachable Assets', function () {
    $earth = Allegiance::factory()->earth()->create();
    $other = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($earth)->create();

    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$earth->id]);
    $companyUnit = CompanyUnit::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'is_commander' => false, 'position' => 0]);

    // Both are in the company pool (no allegiance relation → unrestricted), but
    // "blocked" carries an allegiance limit the unit fails.
    $attachable = Asset::factory()->create();
    $blocked = Asset::factory()->create();
    $blocked->limits()->create([
        'limit_type' => 'restricted',
        'parameter_type' => 'allegiance',
        'parameter_allegiance_id' => $other->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('tos.companies.view', $company->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('available_assets', function ($assets) use ($companyUnit, $attachable, $blocked) {
            $att = collect($assets)->firstWhere('id', $attachable->id);
            $blk = collect($assets)->firstWhere('id', $blocked->id);

            return in_array($companyUnit->id, $att['attachable_company_unit_ids'] ?? [], true)
                && ! in_array($companyUnit->id, $blk['attachable_company_unit_ids'] ?? [], true);
        }));
});

it('rejects hiring a non-Commander unit that would exceed the Commander Scrip budget', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $cmdr = commanderUnit($alle, ['scrip' => 5]);
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
    $cmdr = commanderUnit($alle, ['scrip' => 12]);
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

    $cmdr = commanderUnit($alle, ['scrip' => 10]);
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
    $cmdr = commanderUnit($earth1, ['scrip' => 10]);
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

it('updateSculpt persists the sculpt selection', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();
    $unit = Unit::factory()->withSides()->create(['scrip' => 8]);
    $unit->allegiances()->sync([$alle->id]);
    $sculptA = UnitSculpt::factory()->forUnit($unit)->create();
    $sculptB = UnitSculpt::factory()->forUnit($unit)->create();
    $cu = CompanyUnit::create([
        'company_id' => $company->id,
        'unit_id' => $unit->id,
        'is_commander' => false,
        'position' => 0,
    ]);

    $this->actingAs($this->user)
        ->post(route('tos.companies.units.sculpt', [$company->slug, $cu->id]), ['sculpt_id' => $sculptB->id])
        ->assertRedirect();

    expect($cu->fresh()->sculpt_id)->toBe($sculptB->id);

    // Allow unsetting back to null (default sculpt at render time).
    $this->actingAs($this->user)
        ->post(route('tos.companies.units.sculpt', [$company->slug, $cu->id]), ['sculpt_id' => null])
        ->assertRedirect();

    expect($cu->fresh()->sculpt_id)->toBeNull();
});

it('updateSculpt rejects a sculpt belonging to a different unit', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forUser($this->user)->forAllegiance($alle)->create();
    $unit = Unit::factory()->withSides()->create(['scrip' => 8]);
    $unit->allegiances()->sync([$alle->id]);
    $cu = CompanyUnit::create([
        'company_id' => $company->id,
        'unit_id' => $unit->id,
        'is_commander' => false,
        'position' => 0,
    ]);

    // Sculpt belongs to a wholly different unit — must be rejected.
    $otherUnit = Unit::factory()->withSides()->create();
    $foreignSculpt = UnitSculpt::factory()->forUnit($otherUnit)->create();

    $this->actingAs($this->user)
        ->post(route('tos.companies.units.sculpt', [$company->slug, $cu->id]), ['sculpt_id' => $foreignSculpt->id])
        ->assertStatus(422);

    expect($cu->fresh()->sculpt_id)->toBeNull();
});

it('updateSculpt blocks the owner of another user\'s Company', function () {
    $alle = Allegiance::factory()->earth()->create();
    $company = Company::factory()->forAllegiance($alle)->create(); // some other user
    $unit = Unit::factory()->withSides()->create(['scrip' => 8]);
    $unit->allegiances()->sync([$alle->id]);
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();
    $cu = CompanyUnit::create([
        'company_id' => $company->id,
        'unit_id' => $unit->id,
        'is_commander' => false,
        'position' => 0,
    ]);

    $this->actingAs($this->user)
        ->post(route('tos.companies.units.sculpt', [$company->slug, $cu->id]), ['sculpt_id' => $sculpt->id])
        ->assertForbidden();

    expect($cu->fresh()->sculpt_id)->toBeNull();
});
