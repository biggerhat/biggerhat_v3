<?php

use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\User;
use App\Models\WishlistItem;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wishlist = $this->user->wishlists()->create(['name' => 'My Wishlist']);
});

it('adds a TOS unit to a wishlist', function () {
    $unit = Unit::factory()->withSides()->create();

    $this->actingAs($this->user)
        ->post(route('wishlists.items.add', $this->wishlist), ['type' => 'unit', 'id' => $unit->id])
        ->assertRedirect();

    expect(WishlistItem::where('wishlist_id', $this->wishlist->id)
        ->where('wishlistable_type', Unit::class)
        ->where('wishlistable_id', $unit->id)
        ->exists())->toBeTrue();
});

it('adds a TOS unit sculpt to a wishlist', function () {
    $unit = Unit::factory()->withSides()->create();
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    $this->actingAs($this->user)
        ->post(route('wishlists.items.add', $this->wishlist), ['type' => 'unit_sculpt', 'id' => $sculpt->id])
        ->assertRedirect();

    expect(WishlistItem::where('wishlist_id', $this->wishlist->id)
        ->where('wishlistable_type', UnitSculpt::class)
        ->where('wishlistable_id', $sculpt->id)
        ->exists())->toBeTrue();
});

it('rejects an unknown wishlistable type', function () {
    $this->actingAs($this->user)
        ->post(route('wishlists.items.add', $this->wishlist), ['type' => 'garbage', 'id' => 1])
        ->assertSessionHasErrors('type');
});

it('groups TOS units and sculpts separately when showing a wishlist', function () {
    $unit = Unit::factory()->withSides()->create(['name' => 'Widow']);
    $sculpt = UnitSculpt::factory()->forUnit($unit)->create();

    WishlistItem::create(['wishlist_id' => $this->wishlist->id, 'wishlistable_type' => Unit::class, 'wishlistable_id' => $unit->id]);
    WishlistItem::create(['wishlist_id' => $this->wishlist->id, 'wishlistable_type' => UnitSculpt::class, 'wishlistable_id' => $sculpt->id]);

    $this->actingAs($this->user)
        ->get(route('wishlists.show', $this->wishlist))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Wishlists/Show')
            ->has('items.units', 1)
            ->where('items.units.0.name', 'Widow')
            ->has('items.unit_sculpts', 1)
        );
});

it('surfaces TOS wishlist items in the shared auth.wishlist_items prop', function () {
    $unit = Unit::factory()->withSides()->create();
    WishlistItem::create(['wishlist_id' => $this->wishlist->id, 'wishlistable_type' => Unit::class, 'wishlistable_id' => $unit->id]);

    $this->actingAs($this->user)
        ->get(route('wishlists.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where("auth.wishlist_items.{$this->wishlist->id}.units", [$unit->id]));
});
