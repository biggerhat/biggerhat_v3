<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\CampaignCrewCard;
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
        ->get(route('admin.campaign.crew-cards.index'))
        ->assertForbidden();
});

it('index lists crew cards', function () {
    CampaignCrewCard::factory()->create(['name' => 'Ice Reflection']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('items.0.name', 'Ice Reflection'));
});

it('create page renders', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.create'))
        ->assertOk();
});

it('edit page renders', function () {
    $row = CampaignCrewCard::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.edit', $row->id))
        ->assertOk();
});

it('store creates a crew card', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Ice Reflection',
            'description' => 'Body text.',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect(route('admin.campaign.crew-cards.index'));

    $row = CampaignCrewCard::firstWhere('name', 'Ice Reflection');
    expect($row)->not->toBeNull();
    expect($row->description)->toBe('Body text.');
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Nope',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertForbidden();
});

it('update renames a crew card', function () {
    $row = CampaignCrewCard::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => 'Renamed Effect',
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    expect($row->fresh()->name)->toBe('Renamed Effect');
});

it('delete removes the crew card', function () {
    $row = CampaignCrewCard::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.delete', $row->id))
        ->assertRedirect();

    expect(CampaignCrewCard::find($row->id))->toBeNull();
});
