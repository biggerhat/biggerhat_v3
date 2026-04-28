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

it('store syncs all six link types and primary_body across both tiers', function () {
    $stdAbility = Ability::factory()->general()->create();
    $stdAction = \App\Models\TOS\Action::factory()->create();
    $stdTrigger = \App\Models\TOS\Trigger::factory()->create();
    $primAbility = Ability::factory()->general()->create();
    $primAction = \App\Models\TOS\Action::factory()->create();
    $primTrigger = \App\Models\TOS\Trigger::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.allegiance_cards.store'), [
        'allegiance_id' => $this->allegiance->id,
        'name' => 'Two Tier Card',
        'type' => AllegianceTypeEnum::Earth->value,
        'body' => 'standard body',
        'primary_body' => 'primary body',
        'ability_ids' => [$stdAbility->id],
        'action_ids' => [$stdAction->id],
        'trigger_ids' => [$stdTrigger->id],
        'primary_ability_ids' => [$primAbility->id],
        'primary_action_ids' => [$primAction->id],
        'primary_trigger_ids' => [$primTrigger->id],
    ])->assertRedirect();

    $card = AllegianceCard::where('name', 'Two Tier Card')->first();
    $card->load(['abilities', 'actions', 'triggers', 'primaryAbilities', 'primaryActions', 'primaryTriggers']);
    expect($card->primary_body)->toBe('primary body')
        ->and($card->abilities->pluck('id')->all())->toEqual([$stdAbility->id])
        ->and($card->actions->pluck('id')->all())->toEqual([$stdAction->id])
        ->and($card->triggers->pluck('id')->all())->toEqual([$stdTrigger->id])
        ->and($card->primaryAbilities->pluck('id')->all())->toEqual([$primAbility->id])
        ->and($card->primaryActions->pluck('id')->all())->toEqual([$primAction->id])
        ->and($card->primaryTriggers->pluck('id')->all())->toEqual([$primTrigger->id]);
});
