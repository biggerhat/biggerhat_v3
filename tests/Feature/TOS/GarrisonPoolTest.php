<?php

use App\Enums\TOS\GarrisonFormatEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\TOS\Asset;
use App\Models\TOS\Garrison;
use App\Models\TOS\GarrisonUnit;
use App\Models\TOS\Stratagem;
use App\Models\TOS\Unit;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->allegiance = Allegiance::factory()->earth()->create();
    $this->garrison = Garrison::factory()
        ->forUser($this->user)
        ->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::TwoCommanders) // 3 cmdrs / 75 scrip / 12 stratagems / 1 envoy
        ->create();
});

// ── Units ───────────────────────────────────────────────────────────────

it('adds a hireable Unit to the pool', function () {
    $unit = Unit::factory()->create(['scrip' => 7]);
    $this->allegiance->units()->attach($unit->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.units.add', $this->garrison->slug), [
        'unit_id' => $unit->id,
    ])->assertRedirect();

    expect($this->garrison->fresh()->garrisonUnits)->toHaveCount(1);
});

it('rejects a Unit that is not hireable into the Allegiance', function () {
    // Unit attached to a different (Malifaux) Allegiance and no Earth restriction.
    $other = Allegiance::factory()->malifaux()->create();
    $unit = Unit::factory()->create(['scrip' => 7]);
    $other->units()->attach($unit->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.units.add', $this->garrison->slug), [
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('unit_id');

    expect($this->garrison->fresh()->garrisonUnits)->toHaveCount(0);
});

it('enforces the same-name cap', function () {
    $unit = Unit::factory()->create(['scrip' => 5, 'name' => 'Riders']);
    $this->allegiance->units()->attach($unit->id);

    // Format allows 3 max — pre-seed three same-name rows so a 4th trips it.
    foreach (range(1, 3) as $_) {
        GarrisonUnit::create(['garrison_id' => $this->garrison->id, 'unit_id' => $unit->id, 'is_commander' => false]);
    }

    $this->actingAs($this->user)->post(route('tos.garrisons.units.add', $this->garrison->slug), [
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('unit_id');
});

it('rejects a Commander hire when the cap is full', function () {
    $unit = Unit::factory()->create(['scrip' => 18, 'name' => 'Cmdr']);
    $this->allegiance->units()->attach($unit->id);
    foreach (range(1, 3) as $_) {
        GarrisonUnit::create(['garrison_id' => $this->garrison->id, 'unit_id' => $unit->id, 'is_commander' => true]);
    }

    $this->actingAs($this->user)->post(route('tos.garrisons.units.add', $this->garrison->slug), [
        'unit_id' => $unit->id,
        'is_commander' => true,
    ])->assertSessionHasErrors('unit_id');
});

it('rejects a non-Commander Unit that would push past the Scrip pool', function () {
    $expensive = Unit::factory()->create(['scrip' => 80, 'name' => 'Bigshot']);
    $this->allegiance->units()->attach($expensive->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.units.add', $this->garrison->slug), [
        'unit_id' => $expensive->id,
    ])->assertSessionHasErrors('unit_id');
});

it('removes a Unit from the pool', function () {
    $unit = Unit::factory()->create();
    $row = GarrisonUnit::create(['garrison_id' => $this->garrison->id, 'unit_id' => $unit->id, 'is_commander' => false]);

    $this->actingAs($this->user)->post(route('tos.garrisons.units.remove', [$this->garrison->slug, $row->id]))
        ->assertRedirect();

    expect(GarrisonUnit::find($row->id))->toBeNull();
});

it('blocks removing a Unit that belongs to a different Garrison', function () {
    $other = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)->create();
    $unit = Unit::factory()->create();
    $row = GarrisonUnit::create(['garrison_id' => $other->id, 'unit_id' => $unit->id, 'is_commander' => false]);

    $this->actingAs($this->user)->post(route('tos.garrisons.units.remove', [$this->garrison->slug, $row->id]))
        ->assertNotFound();
});

// ── Assets ──────────────────────────────────────────────────────────────

it('attaches an Asset with a default quantity of 1', function () {
    $asset = Asset::factory()->create(['scrip_cost' => 5]);

    $this->actingAs($this->user)->post(route('tos.garrisons.assets.attach', $this->garrison->slug), [
        'asset_id' => $asset->id,
    ])->assertRedirect();

    expect($this->garrison->fresh()->assets()->where('tos_assets.id', $asset->id)->value('tos_garrison_assets.quantity'))
        ->toBe(1);
});

it('increments the Asset quantity when called twice', function () {
    $asset = Asset::factory()->create(['scrip_cost' => 5]);

    $this->actingAs($this->user)->post(route('tos.garrisons.assets.attach', $this->garrison->slug), ['asset_id' => $asset->id]);
    $this->actingAs($this->user)->post(route('tos.garrisons.assets.attach', $this->garrison->slug), ['asset_id' => $asset->id, 'delta' => 2]);

    expect((int) $this->garrison->fresh()->assets()->where('tos_assets.id', $asset->id)->value('tos_garrison_assets.quantity'))
        ->toBe(3);
});

it('decrements and detaches when delta drops the quantity to zero', function () {
    $asset = Asset::factory()->create(['scrip_cost' => 5]);
    $this->garrison->assets()->attach($asset->id, ['quantity' => 1]);

    $this->actingAs($this->user)->post(route('tos.garrisons.assets.attach', $this->garrison->slug), [
        'asset_id' => $asset->id, 'delta' => -1,
    ]);

    expect($this->garrison->fresh()->assets)->toHaveCount(0);
});

it('rejects an Asset attached to a foreign Allegiance', function () {
    $other = Allegiance::factory()->malifaux()->create();
    $asset = Asset::factory()->create(['scrip_cost' => 5]);
    $asset->allegiances()->attach($other->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.assets.attach', $this->garrison->slug), [
        'asset_id' => $asset->id,
    ])->assertSessionHasErrors('asset_id');
});

it('rejects an Asset that would push the pool over budget', function () {
    $asset = Asset::factory()->create(['scrip_cost' => 80]);

    $this->actingAs($this->user)->post(route('tos.garrisons.assets.attach', $this->garrison->slug), [
        'asset_id' => $asset->id,
    ])->assertSessionHasErrors('asset_id');
});

it('detaches an Asset entirely on the dedicated endpoint', function () {
    $asset = Asset::factory()->create(['scrip_cost' => 5]);
    $this->garrison->assets()->attach($asset->id, ['quantity' => 4]);

    $this->actingAs($this->user)->post(route('tos.garrisons.assets.detach', [$this->garrison->slug, $asset->slug]))
        ->assertRedirect();

    expect($this->garrison->fresh()->assets)->toHaveCount(0);
});

// ── Stratagems ──────────────────────────────────────────────────────────

it('picks an Allegiance-scoped Stratagem', function () {
    $stratagem = Stratagem::factory()->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.stratagems.pick', $this->garrison->slug), [
        'stratagem_id' => $stratagem->id,
    ])->assertRedirect();

    expect($this->garrison->fresh()->stratagems)->toHaveCount(1);
});

it('rejects a Stratagem that does not match the Allegiance', function () {
    $other = Allegiance::factory()->malifaux()->create();
    $stratagem = Stratagem::factory()->forAllegiance($other)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.stratagems.pick', $this->garrison->slug), [
        'stratagem_id' => $stratagem->id,
    ])->assertSessionHasErrors('stratagem_id');
});

it('enforces the Stratagem cap', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::OneCommander)->create(); // cap = 6
    foreach (range(1, 6) as $_) {
        $g->stratagems()->attach(Stratagem::factory()->forAllegiance($this->allegiance)->create()->id);
    }
    $extra = Stratagem::factory()->forAllegiance($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.stratagems.pick', $g->slug), [
        'stratagem_id' => $extra->id,
    ])->assertSessionHasErrors('stratagem_id');
});

it('unpicks a Stratagem', function () {
    $stratagem = Stratagem::factory()->forAllegiance($this->allegiance)->create();
    $this->garrison->stratagems()->attach($stratagem->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.stratagems.unpick', [$this->garrison->slug, $stratagem->slug]))
        ->assertRedirect();

    expect($this->garrison->fresh()->stratagems)->toHaveCount(0);
});

// ── Envoys ──────────────────────────────────────────────────────────────

it('picks an Envoy from the Garrison Allegiance', function () {
    $card = AllegianceCard::factory()->for($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.envoys.pick', $this->garrison->slug), [
        'allegiance_card_id' => $card->id,
    ])->assertRedirect();

    expect($this->garrison->fresh()->envoys)->toHaveCount(1);
});

it('rejects an Envoy from a foreign Allegiance', function () {
    $other = Allegiance::factory()->malifaux()->create();
    $card = AllegianceCard::factory()->for($other)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.envoys.pick', $this->garrison->slug), [
        'allegiance_card_id' => $card->id,
    ])->assertSessionHasErrors('allegiance_card_id');
});

it('rejects an Envoy on a no-Envoy format', function () {
    $g = Garrison::factory()->forUser($this->user)->forAllegiance($this->allegiance)
        ->format(GarrisonFormatEnum::OneCommander)->create(); // envoy_count = 0
    $card = AllegianceCard::factory()->for($this->allegiance)->create();

    $this->actingAs($this->user)->post(route('tos.garrisons.envoys.pick', $g->slug), [
        'allegiance_card_id' => $card->id,
    ])->assertSessionHasErrors('allegiance_card_id');
});

it('rejects an Envoy when the slot is already filled', function () {
    $first = AllegianceCard::factory()->for($this->allegiance)->create();
    $second = AllegianceCard::factory()->for($this->allegiance)->create();
    $this->garrison->envoys()->attach($first->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.envoys.pick', $this->garrison->slug), [
        'allegiance_card_id' => $second->id,
    ])->assertSessionHasErrors('allegiance_card_id');
});

it('unpicks an Envoy', function () {
    $card = AllegianceCard::factory()->for($this->allegiance)->create();
    $this->garrison->envoys()->attach($card->id);

    $this->actingAs($this->user)->post(route('tos.garrisons.envoys.unpick', [$this->garrison->slug, $card->slug]))
        ->assertRedirect();

    expect($this->garrison->fresh()->envoys)->toHaveCount(0);
});

// ── Sculpt selection ────────────────────────────────────────────────────

it('updates the sculpt for a pool unit', function () {
    $unit = Unit::factory()->create();
    $sculptA = \App\Models\TOS\UnitSculpt::factory()->forUnit($unit)->create();
    $sculptB = \App\Models\TOS\UnitSculpt::factory()->forUnit($unit)->create();
    $row = GarrisonUnit::create([
        'garrison_id' => $this->garrison->id,
        'unit_id' => $unit->id,
        'is_commander' => false,
        'sculpt_id' => $sculptA->id,
    ]);

    $this->actingAs($this->user)->post(
        route('tos.garrisons.units.sculpt', [$this->garrison->slug, $row->id]),
        ['sculpt_id' => $sculptB->id]
    )->assertRedirect();

    expect($row->fresh()->sculpt_id)->toBe($sculptB->id);
});

it('rejects a sculpt that belongs to a different unit', function () {
    $unitA = Unit::factory()->create();
    $unitB = Unit::factory()->create();
    $strangerSculpt = \App\Models\TOS\UnitSculpt::factory()->forUnit($unitB)->create();
    $row = GarrisonUnit::create([
        'garrison_id' => $this->garrison->id,
        'unit_id' => $unitA->id,
        'is_commander' => false,
    ]);

    $this->actingAs($this->user)->post(
        route('tos.garrisons.units.sculpt', [$this->garrison->slug, $row->id]),
        ['sculpt_id' => $strangerSculpt->id]
    )->assertStatus(422);

    expect($row->fresh()->sculpt_id)->toBeNull();
});

// ── Format swap (Edit dialog) ───────────────────────────────────────────

it('lets the format swap to a smaller cap and surfaces the resulting violations', function () {
    // Build up a full TwoCommanders pool, then swap to OneCommander (cap 40).
    $cmdr = Unit::factory()->create(['scrip' => 18, 'name' => 'Cmdr']);
    GarrisonUnit::create(['garrison_id' => $this->garrison->id, 'unit_id' => $cmdr->id, 'is_commander' => true]);
    $expensive = Unit::factory()->create(['scrip' => 70, 'name' => 'Bigshot']);
    GarrisonUnit::create(['garrison_id' => $this->garrison->id, 'unit_id' => $expensive->id, 'is_commander' => false]);

    $this->actingAs($this->user)->post(route('tos.garrisons.update', $this->garrison->slug), [
        'name' => $this->garrison->name,
        'format' => GarrisonFormatEnum::OneCommander->value,
    ])->assertRedirect();

    $fresh = $this->garrison->fresh();
    expect($fresh->format)->toBe(GarrisonFormatEnum::OneCommander);
    expect($fresh->violations())->not->toBe([]);
});

// ── Ownership ───────────────────────────────────────────────────────────

it('returns 403 on every pool endpoint for non-owners', function () {
    $other = User::factory()->create();
    $g = Garrison::factory()->forUser($other)->forAllegiance($this->allegiance)->create();
    $unit = Unit::factory()->create();
    $row = GarrisonUnit::create(['garrison_id' => $g->id, 'unit_id' => $unit->id, 'is_commander' => false]);
    $asset = Asset::factory()->create();
    $stratagem = Stratagem::factory()->forAllegiance($this->allegiance)->create();
    $card = AllegianceCard::factory()->for($this->allegiance)->create();

    $this->actingAs($this->user);
    $this->post(route('tos.garrisons.units.add', $g->slug), ['unit_id' => $unit->id])->assertForbidden();
    $this->post(route('tos.garrisons.units.remove', [$g->slug, $row->id]))->assertForbidden();
    $this->post(route('tos.garrisons.assets.attach', $g->slug), ['asset_id' => $asset->id])->assertForbidden();
    $this->post(route('tos.garrisons.assets.detach', [$g->slug, $asset->slug]))->assertForbidden();
    $this->post(route('tos.garrisons.stratagems.pick', $g->slug), ['stratagem_id' => $stratagem->id])->assertForbidden();
    $this->post(route('tos.garrisons.stratagems.unpick', [$g->slug, $stratagem->slug]))->assertForbidden();
    $this->post(route('tos.garrisons.envoys.pick', $g->slug), ['allegiance_card_id' => $card->id])->assertForbidden();
    $this->post(route('tos.garrisons.envoys.unpick', [$g->slug, $card->slug]))->assertForbidden();
});
