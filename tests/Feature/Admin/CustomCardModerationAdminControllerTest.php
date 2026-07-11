<?php

use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use App\Models\User;
use Spatie\Permission\Models\Role;

function ccmSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'super_admin']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('blocks non-super-admins', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.custom_cards.index'))
        ->assertForbidden();
});

it('lists public characters and upgrades by default', function () {
    $admin = ccmSuperAdmin();
    $owner = User::factory()->create();
    CustomCharacter::create([
        'user_id' => $owner->id, 'name' => 'Public Char', 'faction' => 'guild',
        'health' => 10, 'base' => 30, 'defense' => 5, 'willpower' => 5, 'speed' => 5,
        'is_public' => true,
    ]);
    CustomCharacter::create([
        'user_id' => $owner->id, 'name' => 'Private Char', 'faction' => 'guild',
        'health' => 10, 'base' => 30, 'defense' => 5, 'willpower' => 5, 'speed' => 5,
        'is_public' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.custom_cards.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('cards', 1)->where('cards.0.name', 'Public Char'));
});

it('does not crash when a null-faction row (e.g. a Totem template) is in the result set — regression', function () {
    $admin = ccmSuperAdmin();
    $owner = User::factory()->create();
    // Totem templates legitimately have no faction — it's inherited from the
    // leader once attached to a crew. Confirms the moderation listing survives
    // browsing "all"/"private" visibility with one of these in scope.
    CustomCharacter::create([
        'user_id' => $owner->id, 'name' => 'Totem Template', 'faction' => null,
        'is_campaign_totem_template' => true,
        'health' => 3, 'base' => 30, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
        'is_public' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.custom_cards.index', ['visibility' => 'private']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('cards', 1)->where('cards.0.faction', null));
});

it('unpublish sets is_public to false', function () {
    $admin = ccmSuperAdmin();
    $owner = User::factory()->create();
    $character = CustomCharacter::create([
        'user_id' => $owner->id, 'name' => 'To Unpublish', 'faction' => 'guild',
        'health' => 10, 'base' => 30, 'defense' => 5, 'willpower' => 5, 'speed' => 5,
        'is_public' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.custom_cards.unpublish', ['kind' => 'character', 'id' => $character->id]))
        ->assertRedirect();

    expect($character->fresh()->is_public)->toBeFalse();
});

it('destroy soft-deletes the card', function () {
    $admin = ccmSuperAdmin();
    $owner = User::factory()->create();
    $upgrade = CustomUpgrade::create([
        'user_id' => $owner->id, 'name' => 'To Delete', 'domain' => 'character', 'faction' => 'guild',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.custom_cards.delete', ['kind' => 'upgrade', 'id' => $upgrade->id]))
        ->assertRedirect();

    expect(CustomUpgrade::find($upgrade->id))->toBeNull();
    expect(CustomUpgrade::withTrashed()->find($upgrade->id))->not->toBeNull();
});
