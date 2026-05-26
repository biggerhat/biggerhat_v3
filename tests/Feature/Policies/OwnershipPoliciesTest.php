<?php

use App\Models\CrewBuild;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\User;
use App\Models\Wishlist;
use Spatie\Permission\Models\Role;

/**
 * Coverage for the four ownership-based policies introduced to replace
 * ad-hoc `Auth::id() === $model->user_id` checks. Each policy follows the same
 * shape: owner OR super_admin for view/update/delete; viewShare additionally
 * allows public traffic when the resource is flagged public.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

// ─── CustomCharacterPolicy ───

// Helpers — these models predate a factory; build minimal rows directly.
function makeCustomCharacter(int $userId): CustomCharacter
{
    return CustomCharacter::create([
        'user_id' => $userId,
        'name' => 'Test Character',
        'faction' => 'arcanists',
        'health' => 5,
        'defense' => 5,
        'willpower' => 5,
        'speed' => 5,
    ]);
}

function makeCustomUpgrade(int $userId): CustomUpgrade
{
    return CustomUpgrade::create([
        'user_id' => $userId,
        'name' => 'Test Upgrade',
        'domain' => 'character',
    ]);
}

function makeWishlist(int $userId, bool $isPublic = false): Wishlist
{
    return Wishlist::create([
        'user_id' => $userId,
        'name' => 'Test Wishlist',
        'is_public' => $isPublic,
    ]);
}

it('CustomCharacter: owner can view/update/delete; non-owners cannot', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $character = makeCustomCharacter($owner->id);

    expect($owner->can('view', $character))->toBeTrue()
        ->and($owner->can('update', $character))->toBeTrue()
        ->and($owner->can('delete', $character))->toBeTrue()
        ->and($other->can('view', $character))->toBeFalse()
        ->and($other->can('update', $character))->toBeFalse()
        ->and($other->can('delete', $character))->toBeFalse();
});

it('CustomCharacter: super_admin bypasses ownership', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create()->assignRole('super_admin');
    $character = makeCustomCharacter($owner->id);

    expect($admin->can('view', $character))->toBeTrue()
        ->and($admin->can('update', $character))->toBeTrue()
        ->and($admin->can('delete', $character))->toBeTrue();
});

// ─── CustomUpgradePolicy ───

it('CustomUpgrade: owner can view/update/delete; non-owners cannot', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $upgrade = makeCustomUpgrade($owner->id);

    expect($owner->can('update', $upgrade))->toBeTrue()
        ->and($owner->can('delete', $upgrade))->toBeTrue()
        ->and($other->can('update', $upgrade))->toBeFalse()
        ->and($other->can('delete', $upgrade))->toBeFalse();
});

// ─── CrewBuildPolicy ───

it('CrewBuild: owner can view/update/delete; non-owners cannot', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $build = CrewBuild::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

    expect($owner->can('view', $build))->toBeTrue()
        ->and($owner->can('update', $build))->toBeTrue()
        ->and($owner->can('delete', $build))->toBeTrue()
        ->and($other->can('view', $build))->toBeFalse()
        ->and($other->can('update', $build))->toBeFalse()
        ->and($other->can('delete', $build))->toBeFalse();
});

it('CrewBuild: viewShare admits anonymous viewers when public, blocks them when private', function () {
    $owner = User::factory()->create();
    $publicBuild = CrewBuild::factory()->create(['user_id' => $owner->id, 'is_public' => true]);
    $privateBuild = CrewBuild::factory()->create(['user_id' => $owner->id, 'is_public' => false]);

    expect(Gate::forUser(null)->allows('viewShare', $publicBuild))->toBeTrue()
        ->and(Gate::forUser(null)->allows('viewShare', $privateBuild))->toBeFalse()
        ->and($owner->can('viewShare', $privateBuild))->toBeTrue();
});

// ─── WishlistPolicy ───

it('Wishlist: owner can view/update/delete; other users blocked on private list', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $wishlist = makeWishlist($owner->id, isPublic: false);

    expect($owner->can('view', $wishlist))->toBeTrue()
        ->and($owner->can('update', $wishlist))->toBeTrue()
        ->and($owner->can('delete', $wishlist))->toBeTrue()
        ->and($other->can('view', $wishlist))->toBeFalse()
        ->and($other->can('update', $wishlist))->toBeFalse()
        ->and($other->can('delete', $wishlist))->toBeFalse();
});

it('Wishlist: view is allowed when the list is public', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $wishlist = makeWishlist($owner->id, isPublic: true);

    expect($other->can('view', $wishlist))->toBeTrue()
        // Update / delete still owner-only even when public.
        ->and($other->can('update', $wishlist))->toBeFalse()
        ->and($other->can('delete', $wishlist))->toBeFalse();
});

it('Wishlist: viewShare admits anonymous viewers on public lists, blocks on private', function () {
    $owner = User::factory()->create();
    $publicList = makeWishlist($owner->id, isPublic: true);
    $privateList = makeWishlist($owner->id, isPublic: false);

    expect(Gate::forUser(null)->allows('viewShare', $publicList))->toBeTrue()
        ->and(Gate::forUser(null)->allows('viewShare', $privateList))->toBeFalse();
});
