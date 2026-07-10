<?php

use App\Models\Package;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->unit = Unit::factory()->withSides()->create();
    $this->sculpt1 = UnitSculpt::factory()->forUnit($this->unit)->create();
    $this->sculpt2 = UnitSculpt::factory()->forUnit($this->unit)->create();
});

it('requires authentication for the collection index', function () {
    $this->get(route('tos.collection.index'))->assertRedirect('/login');
});

it('renders the TOS collection index for an authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('tos.collection.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Collection/Index'));
});

it('attaches a single unit sculpt to a user collection via toggle', function () {
    $this->actingAs($this->user)->postJson(route('tos.collection.toggle'), [
        'unit_sculpt_id' => $this->sculpt1->id,
    ])->assertRedirect();

    expect($this->user->collectionUnitSculpts()->count())->toBe(1);
    expect($this->user->collectionUnitSculpts()->first()->pivot->quantity)->toBe(1);
});

it('detaches a unit sculpt via toggle with quantity 0', function () {
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.toggle'), [
        'unit_sculpt_id' => $this->sculpt1->id,
        'quantity' => 0,
    ])->assertRedirect();

    expect($this->user->collectionUnitSculpts()->count())->toBe(0);
});

it('updates is_built and is_painted on a single unit sculpt', function () {
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_status'), [
        'unit_sculpt_id' => $this->sculpt1->id,
        'is_built' => true,
        'is_painted' => true,
    ])->assertRedirect();

    $pivot = $this->user->collectionUnitSculpts()->first()->pivot;
    expect((bool) $pivot->is_built)->toBeTrue();
    expect((bool) $pivot->is_painted)->toBeTrue();
});

it('records built_count/painted_count on a Squad unit sculpt', function () {
    $squadUnit = Unit::factory()->squad(9)->withSides()->create();
    $squadSculpt = UnitSculpt::factory()->forUnit($squadUnit)->create();
    $this->user->collectionUnitSculpts()->attach($squadSculpt->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_status'), [
        'unit_sculpt_id' => $squadSculpt->id,
        'built_count' => 6,
        'painted_count' => 4,
    ])->assertRedirect();

    $pivot = $this->user->collectionUnitSculpts()->where('tos_unit_sculpts.id', $squadSculpt->id)->first()->pivot;
    expect((int) $pivot->built_count)->toBe(6);
    expect((int) $pivot->painted_count)->toBe(4);
});

it('clamps built_count/painted_count to the unit\'s squad size', function () {
    $squadUnit = Unit::factory()->squad(9)->withSides()->create();
    $squadSculpt = UnitSculpt::factory()->forUnit($squadUnit)->create();
    $this->user->collectionUnitSculpts()->attach($squadSculpt->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_status'), [
        'unit_sculpt_id' => $squadSculpt->id,
        'built_count' => 999,
        'painted_count' => 999,
    ])->assertRedirect();

    $pivot = $this->user->collectionUnitSculpts()->where('tos_unit_sculpts.id', $squadSculpt->id)->first()->pivot;
    expect((int) $pivot->built_count)->toBe(9);
    expect((int) $pivot->painted_count)->toBe(9);
});

it('zeroes built_count/painted_count for a non-Squad unit even if submitted', function () {
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_status'), [
        'unit_sculpt_id' => $this->sculpt1->id,
        'built_count' => 5,
    ])->assertRedirect();

    $pivot = $this->user->collectionUnitSculpts()->first()->pivot;
    expect((int) $pivot->built_count)->toBe(0);
});

it('exposes squad_size and derives is_built/is_painted from the counts on the collection payload', function () {
    $squadUnit = Unit::factory()->squad(9)->withSides()->create();
    $squadSculpt = UnitSculpt::factory()->forUnit($squadUnit)->create();
    $this->user->collectionUnitSculpts()->attach($squadSculpt->id, [
        'quantity' => 1, 'built_count' => 9, 'painted_count' => 6,
    ]);

    $this->actingAs($this->user)
        ->get(route('tos.collection.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('collection.0.squad_size', 9)
            ->where('collection.0.built_count', 9)
            ->where('collection.0.painted_count', 6)
            // Fully built (9/9) but not fully painted (6/9).
            ->where('collection.0.is_built', true)
            ->where('collection.0.is_painted', false));
});

it('a non-Squad sculpt has null squad_size and keeps the plain boolean semantics', function () {
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1, 'is_painted' => true]);

    $this->actingAs($this->user)
        ->get(route('tos.collection.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('collection.0.squad_size', null)
            ->where('collection.0.is_painted', true));
});

it('adds a unit via add-unit, attaching its first sculpt', function () {
    $this->actingAs($this->user)->postJson(route('tos.collection.add_unit'), [
        'unit_id' => $this->unit->id,
    ])->assertRedirect();

    expect($this->user->collectionUnitSculpts()->count())->toBe(1);
});

it('removes many unit sculpts in one request via remove-bulk', function () {
    $this->user->collectionUnitSculpts()->attach([
        $this->sculpt1->id => ['quantity' => 1],
        $this->sculpt2->id => ['quantity' => 1],
    ]);

    $this->actingAs($this->user)->postJson(route('tos.collection.remove_bulk'), [
        'unit_sculpt_ids' => [$this->sculpt1->id],
    ])->assertRedirect();

    $remaining = $this->user->collectionUnitSculpts()->pluck('tos_unit_sculpts.id')->toArray();
    expect($remaining)->toBe([$this->sculpt2->id]);
});

it('marks many unit sculpts built via update-status-bulk', function () {
    $this->user->collectionUnitSculpts()->attach([
        $this->sculpt1->id => ['quantity' => 1, 'is_built' => false],
        $this->sculpt2->id => ['quantity' => 1, 'is_built' => false],
    ]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_status_bulk'), [
        'unit_sculpt_ids' => [$this->sculpt1->id, $this->sculpt2->id],
        'is_built' => true,
    ])->assertRedirect();

    $built = $this->user->collectionUnitSculpts()->wherePivot('is_built', true)->pluck('tos_unit_sculpts.id')->sort()->values()->toArray();
    expect($built)->toBe([$this->sculpt1->id, $this->sculpt2->id]);
});

it('does not touch other users collections via bulk endpoints', function () {
    $otherUser = User::factory()->create();
    $otherUser->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1, 'is_built' => false]);
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1, 'is_built' => false]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_status_bulk'), [
        'unit_sculpt_ids' => [$this->sculpt1->id],
        'is_built' => true,
    ])->assertRedirect();

    $otherPivot = $otherUser->collectionUnitSculpts()->first()->pivot;
    $myPivot = $this->user->collectionUnitSculpts()->first()->pivot;
    expect((bool) $otherPivot->is_built)->toBeFalse();
    expect((bool) $myPivot->is_built)->toBeTrue();
});

it('rejects bulk endpoints when not authenticated', function () {
    $this->postJson(route('tos.collection.remove_bulk'), [
        'unit_sculpt_ids' => [$this->sculpt1->id],
    ])->assertUnauthorized();
});

it('surfaces owned unit sculpts in the shared auth.collection_unit_sculpt_ids prop', function () {
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1]);

    $this->actingAs($this->user)
        ->get(route('tos.units.view', $this->sculpt1->slug))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.collection_unit_sculpt_ids', [$this->sculpt1->id]));
});

it('scopes owned packages to TOS-flagged packages only', function () {
    $tosPackage = Package::factory()->tos()->create();
    $malifauxPackage = Package::factory()->create();
    $this->user->collectionPackages()->attach([$tosPackage->id, $malifauxPackage->id]);

    // owned_packages is Inertia::defer'd — hit it with partial-reload headers
    // so the deferred closure runs and we can assert against the resolved value.
    $manifest = public_path('build/manifest.json');
    $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

    $response = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'TOS/Collection/Index',
            'X-Inertia-Partial-Data' => 'owned_packages',
        ])
        ->get(route('tos.collection.index'));

    $response->assertOk();
    $payload = $response->json();
    expect($payload['props']['owned_packages'])->toHaveCount(1)
        ->and($payload['props']['owned_packages'][0]['id'])->toBe($tosPackage->id);
});

it('lazily generates a share code the first time a user visits their TOS collection', function () {
    expect($this->user->tos_collection_share_code)->toBeNull();

    $this->actingAs($this->user)
        ->get(route('tos.collection.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('is_owner', true)->where('is_public', false)->has('share_code'));

    expect($this->user->fresh()->tos_collection_share_code)->not->toBeNull();
});

it('404s a TOS collection share link for an unknown code', function () {
    $this->get(route('tos.collection.share', ['shareCode' => 'nonexistent']))->assertNotFound();
});

it('403s a private TOS collection share link for a stranger', function () {
    $this->user->update(['tos_collection_share_code' => 'abc123', 'tos_collection_is_public' => false]);

    $this->get(route('tos.collection.share', ['shareCode' => 'abc123']))->assertForbidden();
});

it('allows the owner to view their own private TOS collection share link', function () {
    $this->user->update(['tos_collection_share_code' => 'abc123', 'tos_collection_is_public' => false]);

    $this->actingAs($this->user)
        ->get(route('tos.collection.share', ['shareCode' => 'abc123']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('is_owner', true));
});

it('lets anyone view a public TOS collection share link, read-only', function () {
    $this->user->update(['tos_collection_share_code' => 'abc123', 'tos_collection_is_public' => true, 'name' => 'Sculptor']);
    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1]);

    $this->get(route('tos.collection.share', ['shareCode' => 'abc123']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('TOS/Collection/Index')
            ->where('is_owner', false)
            ->where('is_public', true)
            ->where('owner_name', 'Sculptor')
            ->has('collection', 1)
        );
});

it('toggles TOS collection visibility and generates a share code if missing', function () {
    expect($this->user->tos_collection_share_code)->toBeNull();

    $this->actingAs($this->user)->postJson(route('tos.collection.toggle_public'))->assertRedirect();

    $this->user->refresh();
    expect((bool) $this->user->tos_collection_is_public)->toBeTrue()
        ->and($this->user->tos_collection_share_code)->not->toBeNull();

    $this->actingAs($this->user)->postJson(route('tos.collection.toggle_public'))->assertRedirect();
    expect((bool) $this->user->fresh()->tos_collection_is_public)->toBeFalse();
});

it('requires authentication to toggle TOS collection visibility', function () {
    $this->postJson(route('tos.collection.toggle_public'))->assertUnauthorized();
});

it('attaches an Adjunct-limit asset to a user collection via toggle-asset', function () {
    $asset = \App\Models\TOS\Asset::factory()->adjunct(50)->create();

    $this->actingAs($this->user)->postJson(route('tos.collection.toggle_asset'), [
        'asset_id' => $asset->id,
    ])->assertRedirect();

    expect($this->user->collectionAssets()->count())->toBe(1);
    expect($this->user->collectionAssets()->first()->pivot->quantity)->toBe(1);
});

it('detaches an Adjunct asset via toggle-asset with quantity 0', function () {
    $asset = \App\Models\TOS\Asset::factory()->adjunct(50)->create();
    $this->user->collectionAssets()->attach($asset->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.toggle_asset'), [
        'asset_id' => $asset->id,
        'quantity' => 0,
    ])->assertRedirect();

    expect($this->user->collectionAssets()->count())->toBe(0);
});

it('rejects adding a non-Adjunct asset to the collection', function () {
    $asset = \App\Models\TOS\Asset::factory()->unique()->create();

    $this->actingAs($this->user)->postJson(route('tos.collection.toggle_asset'), [
        'asset_id' => $asset->id,
    ])->assertStatus(422);

    expect($this->user->collectionAssets()->count())->toBe(0);
});

it('updates is_built and is_painted on a collected Adjunct asset', function () {
    $asset = \App\Models\TOS\Asset::factory()->adjunct(50)->create();
    $this->user->collectionAssets()->attach($asset->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('tos.collection.update_asset_status'), [
        'asset_id' => $asset->id,
        'is_built' => true,
        'is_painted' => true,
    ])->assertRedirect();

    $pivot = $this->user->collectionAssets()->first()->pivot;
    expect((bool) $pivot->is_built)->toBeTrue();
    expect((bool) $pivot->is_painted)->toBeTrue();
});

it('folds owned Adjunct assets into the Units totals, allegiance stats, and collection list', function () {
    $allegiance = \App\Models\TOS\Allegiance::factory()->create();
    $this->unit->allegiances()->attach($allegiance->id);

    $asset = \App\Models\TOS\Asset::factory()->adjunct(50)->forAllegiance($allegiance)->create();
    $unownedAsset = \App\Models\TOS\Asset::factory()->adjunct(30)->create();
    // A non-Adjunct asset must never count toward Units totals.
    \App\Models\TOS\Asset::factory()->unique()->create();

    $this->user->collectionUnitSculpts()->attach($this->sculpt1->id, ['quantity' => 1]);
    $this->user->collectionAssets()->attach($asset->id, ['quantity' => 2]);

    $response = $this->actingAs($this->user)->get(route('tos.collection.index'));

    $response->assertOk()->assertInertia(fn ($p) => $p->component('TOS/Collection/Index')
        // 1 Unit + 2 Adjunct assets (owned + unowned) = 3; owned = 1 Unit + 1 Adjunct asset = 2.
        ->where('totals.units', 3)
        ->where('totals.owned_units', 2)
        ->where('totals.owned_sculpts', 3) // 1 sculpt qty + 2 asset qty
        ->has('collection', 2)
        ->where('collection.0.type', 'unit_sculpt')
        ->where('collection.1.type', 'asset')
        ->where('collection.1.asset_id', $asset->id)
        ->where('collection.1.quantity', 2)
    );

    $stats = collect($response->viewData('page')['props']['allegiance_stats']);
    $stat = $stats->firstWhere('allegiance', $allegiance->slug);

    expect($stat)->not->toBeNull()
        ->and($stat['total'])->toBe(2) // the Unit + the Adjunct asset both share this allegiance
        ->and($stat['owned'])->toBe(2)
        ->and($unownedAsset)->not->toBeNull();
});

it('surfaces owned Adjunct assets in the shared auth.collection_asset_ids prop', function () {
    $asset = \App\Models\TOS\Asset::factory()->adjunct(50)->create();
    $this->user->collectionAssets()->attach($asset->id, ['quantity' => 1]);

    $this->actingAs($this->user)
        ->get(route('tos.units.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('auth.collection_asset_ids', [$asset->id]));
});
