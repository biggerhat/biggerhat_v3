<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\AllegianceCard;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([
        PermissionEnum::ViewTosAllegianceCard,
        PermissionEnum::EditTosAllegianceCard,
        PermissionEnum::DeleteTosAllegianceCard,
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
    $this->allegiance = Allegiance::factory()->create();
});

it('index denies users without view_tos_allegiance_card', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.allegiance_cards.index'))->assertForbidden();
});

it('store creates a card and syncs abilities', function () {
    $a1 = Ability::factory()->general()->create();
    $a2 = Ability::factory()->general()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.allegiance_cards.store'), [
        'allegiance_id' => $this->allegiance->id,
        'name' => 'Demo Card',
        'type' => AllegianceTypeEnum::Earth->value,
        'body' => 'body',
        'ability_ids' => [$a1->id, $a2->id],
    ])->assertRedirect(route('admin.tos.allegiance_cards.index'));

    $card = AllegianceCard::where('name', 'Demo Card')->first();
    expect($card)->not->toBeNull()
        ->and($card->abilities->pluck('id')->all())->toEqualCanonicalizing([$a1->id, $a2->id]);
});

it('store rejects invalid type', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.allegiance_cards.store'), [
        'allegiance_id' => $this->allegiance->id,
        'name' => 'Bad',
        'type' => 'not-a-type',
    ])->assertStatus(422);
});

it('update modifies an existing card and re-syncs abilities', function () {
    $card = AllegianceCard::factory()->withAbilities(2)->create();
    $newAbility = Ability::factory()->general()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.allegiance_cards.update', $card->slug), [
        'allegiance_id' => $card->allegiance_id,
        'name' => 'Renamed',
        'type' => AllegianceTypeEnum::Earth->value,
        'ability_ids' => [$newAbility->id],
    ])->assertRedirect();

    $card->refresh();
    expect($card->name)->toBe('Renamed')
        ->and($card->abilities->pluck('id')->all())->toEqual([$newAbility->id]);
});

it('delete removes the card', function () {
    $card = AllegianceCard::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.allegiance_cards.delete', $card->slug))
        ->assertRedirect(route('admin.tos.allegiance_cards.index'));

    expect(AllegianceCard::find($card->id))->toBeNull();
});

it('store denies users without edit_tos_allegiance_card', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAllegianceCard->value);

    $this->actingAs($viewer)->post(route('admin.tos.allegiance_cards.store'), [
        'allegiance_id' => $this->allegiance->id,
        'name' => 'Blocked',
        'type' => 'earth',
    ])->assertForbidden();

    expect(AllegianceCard::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_allegiance_card', function () {
    $card = AllegianceCard::factory()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAllegianceCard->value);

    $this->actingAs($viewer)->post(route('admin.tos.allegiance_cards.update', $card->slug), [
        'allegiance_id' => $card->allegiance_id,
        'name' => 'Hijacked',
        'type' => 'earth',
    ])->assertForbidden();

    expect($card->fresh()->name)->toBe('Original');
});
