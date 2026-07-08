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
