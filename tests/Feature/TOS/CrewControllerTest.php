<?php

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\Crew;
use App\Models\TOS\CrewUnit;
use App\Models\TOS\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects guests trying to view the crews index', function () {
    $this->get(route('tos.crews.index'))->assertRedirect(route('login'));
});

it('lists only the current user\'s crews', function () {
    $alle = Allegiance::factory()->earth()->create();
    $mine = Crew::factory()->forUser($this->user)->forAllegiance($alle)->create(['name' => 'Mine']);
    Crew::factory()->forAllegiance($alle)->create(['name' => 'Theirs']); // some other user

    $this->actingAs($this->user)->get(route('tos.crews.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TOS/Crews/Index')
            ->has('crews', 1)
            ->where('crews.0.id', $mine->id)
        );
});

it('creates a crew bound to the current user', function () {
    $alle = Allegiance::factory()->earth()->create();

    $this->actingAs($this->user)->post(route('tos.crews.store'), [
        'name' => 'The Iron Vanguard',
        'allegiance_id' => $alle->id,
    ])->assertRedirect();

    $crew = Crew::where('name', 'The Iron Vanguard')->first();
    expect($crew)->not->toBeNull()
        ->and($crew->user_id)->toBe($this->user->id)
        ->and($crew->allegiance_id)->toBe($alle->id);
});

it('blocks viewing another user\'s crew', function () {
    $alle = Allegiance::factory()->earth()->create();
    $crew = Crew::factory()->forAllegiance($alle)->create();

    $this->actingAs($this->user)->get(route('tos.crews.view', $crew->slug))->assertForbidden();
});

it('rejects adding a unit that is not hireable into the crew\'s Allegiance', function () {
    $earth = Allegiance::factory()->earth()->create();
    $malifaux = Allegiance::factory()->malifaux()->create();
    $crew = Crew::factory()->forUser($this->user)->forAllegiance($earth)->create();

    // Unit attached only to a Malifaux Allegiance, no Earth restriction.
    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$malifaux->id]);

    $this->actingAs($this->user)->post(route('tos.crews.units.add', $crew->slug), [
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors(['unit_id']);

    expect($crew->crewUnits()->count())->toBe(0);
});

it('adds a hireable unit and tracks Commander promotion uniquely', function () {
    $alle = Allegiance::factory()->earth()->create();
    $crew = Crew::factory()->forUser($this->user)->forAllegiance($alle)->create();

    $u1 = Unit::factory()->withSides()->create();
    $u1->allegiances()->sync([$alle->id]);
    $u2 = Unit::factory()->withSides()->create();
    $u2->allegiances()->sync([$alle->id]);

    // First unit promoted to Commander.
    $this->actingAs($this->user)->post(route('tos.crews.units.add', $crew->slug), [
        'unit_id' => $u1->id,
        'is_commander' => true,
    ])->assertRedirect();

    // Second unit also promoted — should demote the first.
    $this->actingAs($this->user)->post(route('tos.crews.units.add', $crew->slug), [
        'unit_id' => $u2->id,
        'is_commander' => true,
    ])->assertRedirect();

    $crew->refresh();
    $commanders = $crew->crewUnits()->where('is_commander', true)->pluck('unit_id');
    expect($commanders->all())->toBe([$u2->id]);
});

it('rejects attaching an Asset that fails its Allegiance limit', function () {
    $earth = Allegiance::factory()->earth()->create();
    $other = Allegiance::factory()->earth()->create();
    $crew = Crew::factory()->forUser($this->user)->forAllegiance($earth)->create();

    $unit = Unit::factory()->withSides()->create();
    $unit->allegiances()->sync([$earth->id]);
    $crewUnit = CrewUnit::create(['crew_id' => $crew->id, 'unit_id' => $unit->id, 'is_commander' => false, 'position' => 0]);

    $asset = Asset::factory()->create();
    $asset->limits()->create([
        'limit_type' => 'restricted',
        'parameter_type' => 'allegiance',
        'parameter_allegiance_id' => $other->id,
    ]);

    $this->actingAs($this->user)->post(route('tos.crews.assets.attach', [$crew->slug, $crewUnit->id]), [
        'asset_id' => $asset->id,
    ])->assertSessionHasErrors(['asset_id']);

    expect($crewUnit->assets()->count())->toBe(0);
});

it('Neutral units can be hired into matching-type Allegiances', function () {
    $earth1 = Allegiance::factory()->earth()->create();
    $crew = Crew::factory()->forUser($this->user)->forAllegiance($earth1)->create();

    // Neutral Earth unit — no allegiance attachments, restriction set.
    $neutral = Unit::factory()->neutralFor(AllegianceTypeEnum::Earth)->withSides()->create();

    $this->actingAs($this->user)->post(route('tos.crews.units.add', $crew->slug), [
        'unit_id' => $neutral->id,
    ])->assertRedirect();

    expect($crew->crewUnits()->count())->toBe(1);
});
