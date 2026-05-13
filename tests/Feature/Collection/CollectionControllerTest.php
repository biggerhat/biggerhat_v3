<?php

use App\Models\Character;
use App\Models\Miniature;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->character = Character::factory()->create();
    $this->miniature1 = Miniature::factory()->create(['character_id' => $this->character->id]);
    $this->miniature2 = Miniature::factory()->create(['character_id' => $this->character->id]);
    $this->miniature3 = Miniature::factory()->create(['character_id' => $this->character->id]);
});

// ─── Single mutations (back-fill coverage of pre-existing endpoints) ───

it('attaches a single miniature to a user collection via toggle', function () {
    $this->actingAs($this->user)->postJson(route('collection.toggle'), [
        'miniature_id' => $this->miniature1->id,
    ])->assertRedirect();

    expect($this->user->collectionMiniatures()->count())->toBe(1);
    expect($this->user->collectionMiniatures()->first()->pivot->quantity)->toBe(1);
});

it('removes a miniature via remove', function () {
    $this->user->collectionMiniatures()->attach($this->miniature1->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('collection.remove'), [
        'miniature_id' => $this->miniature1->id,
    ])->assertRedirect();

    expect($this->user->collectionMiniatures()->count())->toBe(0);
});

it('updates is_built and is_painted on a single miniature', function () {
    $this->user->collectionMiniatures()->attach($this->miniature1->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('collection.update_status'), [
        'miniature_id' => $this->miniature1->id,
        'is_built' => true,
        'is_painted' => true,
    ])->assertRedirect();

    $pivot = $this->user->collectionMiniatures()->first()->pivot;
    expect((bool) $pivot->is_built)->toBeTrue();
    expect((bool) $pivot->is_painted)->toBeTrue();
});

// ─── Bulk endpoints (new) ───

it('removes many miniatures in one request via remove-bulk', function () {
    $this->user->collectionMiniatures()->attach([
        $this->miniature1->id => ['quantity' => 1],
        $this->miniature2->id => ['quantity' => 1],
        $this->miniature3->id => ['quantity' => 1],
    ]);

    $this->actingAs($this->user)->postJson(route('collection.remove_bulk'), [
        'miniature_ids' => [$this->miniature1->id, $this->miniature2->id],
    ])->assertRedirect();

    $remaining = $this->user->collectionMiniatures()->pluck('miniatures.id')->toArray();
    expect($remaining)->toBe([$this->miniature3->id]);
});

it('rejects an empty miniature_ids array for remove-bulk', function () {
    $this->actingAs($this->user)->postJson(route('collection.remove_bulk'), [
        'miniature_ids' => [],
    ])->assertStatus(422);
});

it('marks many miniatures built in one request via update-status-bulk', function () {
    $this->user->collectionMiniatures()->attach([
        $this->miniature1->id => ['quantity' => 1, 'is_built' => false],
        $this->miniature2->id => ['quantity' => 1, 'is_built' => false],
        $this->miniature3->id => ['quantity' => 1, 'is_built' => false],
    ]);

    $this->actingAs($this->user)->postJson(route('collection.update_status_bulk'), [
        'miniature_ids' => [$this->miniature1->id, $this->miniature2->id],
        'is_built' => true,
    ])->assertRedirect();

    $built = $this->user->collectionMiniatures()->wherePivot('is_built', true)->pluck('miniatures.id')->sort()->values()->toArray();
    expect($built)->toBe([$this->miniature1->id, $this->miniature2->id]);
});

it('marks many miniatures painted (and leaves is_built untouched) via update-status-bulk', function () {
    $this->user->collectionMiniatures()->attach([
        $this->miniature1->id => ['quantity' => 1, 'is_built' => true, 'is_painted' => false],
        $this->miniature2->id => ['quantity' => 1, 'is_built' => false, 'is_painted' => false],
    ]);

    $this->actingAs($this->user)->postJson(route('collection.update_status_bulk'), [
        'miniature_ids' => [$this->miniature1->id, $this->miniature2->id],
        'is_painted' => true,
    ])->assertRedirect();

    $m1 = $this->user->collectionMiniatures()->where('miniatures.id', $this->miniature1->id)->first()->pivot;
    $m2 = $this->user->collectionMiniatures()->where('miniatures.id', $this->miniature2->id)->first()->pivot;
    expect((bool) $m1->is_painted)->toBeTrue();
    expect((bool) $m1->is_built)->toBeTrue();   // preserved
    expect((bool) $m2->is_painted)->toBeTrue();
    expect((bool) $m2->is_built)->toBeFalse();  // preserved
});

it('no-ops update-status-bulk when neither flag is provided', function () {
    $this->user->collectionMiniatures()->attach($this->miniature1->id, ['quantity' => 1, 'is_built' => true]);

    $this->actingAs($this->user)->postJson(route('collection.update_status_bulk'), [
        'miniature_ids' => [$this->miniature1->id],
    ])->assertRedirect();

    $pivot = $this->user->collectionMiniatures()->first()->pivot;
    expect((bool) $pivot->is_built)->toBeTrue();
});

it('does not touch other users collections via bulk endpoints', function () {
    $otherUser = User::factory()->create();
    $otherUser->collectionMiniatures()->attach($this->miniature1->id, ['quantity' => 1, 'is_built' => false]);
    $this->user->collectionMiniatures()->attach($this->miniature1->id, ['quantity' => 1, 'is_built' => false]);

    $this->actingAs($this->user)->postJson(route('collection.update_status_bulk'), [
        'miniature_ids' => [$this->miniature1->id],
        'is_built' => true,
    ])->assertRedirect();

    $otherPivot = $otherUser->collectionMiniatures()->first()->pivot;
    $myPivot = $this->user->collectionMiniatures()->first()->pivot;
    expect((bool) $otherPivot->is_built)->toBeFalse();
    expect((bool) $myPivot->is_built)->toBeTrue();
});

// ─── Add-many (Add All from Faction/Keyword pages) ───

it('adds many characters miniatures without duplicating already-owned rows', function () {
    $character2 = Character::factory()->create();
    $miniature4 = Miniature::factory()->create(['character_id' => $character2->id, 'version' => \App\Enums\SculptVersionEnum::FourthEdition->value]);
    $this->miniature1->update(['version' => \App\Enums\SculptVersionEnum::FourthEdition->value]);

    // Already own one of them
    $this->user->collectionMiniatures()->attach($this->miniature1->id, ['quantity' => 1]);

    $this->actingAs($this->user)->postJson(route('collection.add_characters'), [
        'character_ids' => [$this->character->id, $character2->id],
    ])->assertRedirect();

    // No duplicate rows for miniature1
    expect($this->user->collectionMiniatures()->where('miniatures.id', $this->miniature1->id)->count())->toBe(1);
    // miniature4 is now attached
    expect($this->user->collectionMiniatures()->where('miniatures.id', $miniature4->id)->exists())->toBeTrue();
});

it('rejects bulk endpoints when not authenticated', function () {
    $this->postJson(route('collection.remove_bulk'), [
        'miniature_ids' => [$this->miniature1->id],
    ])->assertUnauthorized();

    $this->postJson(route('collection.update_status_bulk'), [
        'miniature_ids' => [$this->miniature1->id],
        'is_built' => true,
    ])->assertUnauthorized();
});
