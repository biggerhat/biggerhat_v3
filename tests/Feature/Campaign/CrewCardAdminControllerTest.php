<?php

use App\Enums\CharacterStationEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Character;
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

it('index includes each row\'s linked master', function () {
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    CampaignCrewCard::factory()->create(['name' => 'Ice Reflection', 'master_id' => $master->id]);
    CampaignCrewCard::factory()->create(['name' => 'Generic Effect']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.crew-cards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('items.0.master', null)
            ->where('items.1.master.display_name', $master->display_name));
});

it('store creates a crew card linked to a master', function () {
    $master = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Ice Reflection',
            'description' => 'Body text.',
            'master_id' => $master->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect(route('admin.campaign.crew-cards.index'));

    $row = CampaignCrewCard::firstWhere('name', 'Ice Reflection');
    expect($row)->not->toBeNull();
    expect($row->master_id)->toBe($master->id);
});

it('store rejects a non-Master character as master_id', function () {
    $minion = Character::factory()->create(['station' => CharacterStationEnum::Minion->value]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.store'), [
            'name' => 'Bad Link',
            'master_id' => $minion->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertSessionHasErrors('master_id');
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

it('update re-assigns master_id, including clearing it back to generic', function () {
    $masterA = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    $masterB = Character::factory()->create(['station' => CharacterStationEnum::Master->value]);
    $row = CampaignCrewCard::factory()->create(['master_id' => $masterA->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => $row->name,
            'master_id' => $masterB->id,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    expect($row->fresh()->master_id)->toBe($masterB->id);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.update', $row->id), [
            'name' => $row->name,
            'master_id' => null,
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ])
        ->assertRedirect();

    expect($row->fresh()->master_id)->toBeNull();
});

it('delete removes the crew card', function () {
    $row = CampaignCrewCard::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.crew-cards.delete', $row->id))
        ->assertRedirect();

    expect(CampaignCrewCard::find($row->id))->toBeNull();
});
