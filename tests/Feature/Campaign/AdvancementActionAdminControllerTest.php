<?php

use App\Enums\PermissionEnum;
use App\Models\Action;
use App\Models\Campaign\AdvancementAction;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_campaign_catalog', function () {
    $this->stranger->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $this->actingAs($this->stranger)
        ->get(route('admin.campaign.advancement-action.index'))
        ->assertForbidden();
});

it('store creates a bespoke Action row with its own stat block', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-action.store'), [
            'flip_value' => 6,
            'is_joker' => false,
            'is_always_available' => false,
            'is_signature' => true,
            'talent_name' => 'Bespoke Talent',
            'effect_text' => 'Does a thing.',
            'action_id' => null,
            'stat_block' => ['type' => 'tactical', 'stat' => 5],
        ])
        ->assertRedirect(route('admin.campaign.advancement-action.index'));

    $row = AdvancementAction::firstWhere('talent_name', 'Bespoke Talent');
    expect($row)->not->toBeNull();
    expect($row->action_id)->toBeNull();
    expect($row->stat_block['stat'])->toBe(5);
    expect($row->is_signature)->toBeTrue();
});

it('store links an Action Lookup row', function () {
    $action = Action::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-action.store'), [
            'flip_value' => 9,
            'is_joker' => false,
            'is_always_available' => false,
            'is_signature' => false,
            'talent_name' => 'Reused Action',
            'effect_text' => 'Gains an existing action.',
            'action_id' => $action->id,
        ])
        ->assertRedirect();

    $row = AdvancementAction::firstWhere('talent_name', 'Reused Action');
    expect($row->action_id)->toBe($action->id);
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.advancement-action.store'), [
            'is_joker' => false, 'is_always_available' => false, 'is_signature' => false,
            'talent_name' => 'Nope', 'effect_text' => 'x',
        ])
        ->assertForbidden();
});

it('store rejects a non-existent Action lookup id', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-action.store'), [
            'is_joker' => false, 'is_always_available' => false, 'is_signature' => false,
            'talent_name' => 'Bad Link', 'effect_text' => 'x',
            'action_id' => 999999,
        ])
        ->assertSessionHasErrors('action_id');
});

it('update changes an Action advancement row', function () {
    $row = AdvancementAction::factory()->create(['talent_name' => 'Old']);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-action.update', $row->id), [
            'flip_value' => $row->flip_value,
            'is_joker' => false, 'is_always_available' => false, 'is_signature' => false,
            'talent_name' => 'New',
            'effect_text' => $row->effect_text,
        ])
        ->assertRedirect();

    expect($row->fresh()->talent_name)->toBe('New');
});

it('delete removes an Action advancement row', function () {
    $row = AdvancementAction::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-action.delete', $row->id))
        ->assertRedirect();

    expect(AdvancementAction::find($row->id))->toBeNull();
});
