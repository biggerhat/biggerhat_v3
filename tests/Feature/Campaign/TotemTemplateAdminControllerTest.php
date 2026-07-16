<?php

use App\Enums\PermissionEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\CustomCharacter;
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

function makeTotemTemplate(User $user, array $overrides = []): CustomCharacter
{
    return CustomCharacter::create(array_merge([
        'user_id' => $user->id,
        'name' => 'Totem',
        'display_name' => 'Totem',
        'is_campaign_totem_template' => true,
        'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
        'base' => '30',
    ], $overrides));
}

it('index denies users without view_campaign_catalog', function () {
    // Can reach campaign mode (passes the campaign.access 404 gate) but lacks
    // the catalog view permission → 403.
    $this->stranger->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $this->actingAs($this->stranger)
        ->get(route('admin.campaign.totem-templates.index'))
        ->assertForbidden();
});

it('store creates a totem template with linked actions, signatures, and abilities', function () {
    $a1 = Action::factory()->create();
    $a2 = Action::factory()->create();
    $ability = Ability::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.totem-templates.store'), [
            'name' => 'Wicked Doll',
            'faction' => null,
            'base' => null,
            'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
            'campaign_is_black_joker_totem' => false,
            'campaign_is_red_joker_totem' => false,
            'campaign_totem_special_replace' => false,
            'action_ids' => [$a1->id, $a2->id],
            'signature_action_ids' => [$a2->id],
            'ability_ids' => [$ability->id],
        ])
        ->assertRedirect(route('admin.campaign.totem-templates.index'));

    $totem = CustomCharacter::where('is_campaign_totem_template', true)->firstWhere('name', 'Wicked Doll');
    expect($totem)->not->toBeNull();
    // Faction stays null; base defaults to 30mm when left blank.
    expect($totem->faction)->toBeNull();
    expect($totem->base?->value)->toBe(30);

    expect($totem->campaignTotemActions()->pluck('actions.id')->all())->toEqualCanonicalizing([$a1->id, $a2->id]);
    expect($totem->campaignTotemAbilities()->pluck('abilities.id')->all())->toEqual([$ability->id]);

    // Only a2 is flagged signature.
    $signature = $totem->campaignTotemActions()->wherePivot('is_signature_action', true)->pluck('actions.id')->all();
    expect($signature)->toEqual([$a2->id]);
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.totem-templates.store'), [
            'name' => 'Nope', 'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
            'campaign_is_black_joker_totem' => false,
            'campaign_is_red_joker_totem' => false,
            'campaign_totem_special_replace' => false,
        ])
        ->assertForbidden();
});

it('store rejects a non-existent linked action', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.totem-templates.store'), [
            'name' => 'Bad Link', 'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
            'campaign_is_black_joker_totem' => false,
            'campaign_is_red_joker_totem' => false,
            'campaign_totem_special_replace' => false,
            'action_ids' => [999999],
        ])
        ->assertSessionHasErrors('action_ids.0');
});

it('update re-syncs linked actions and abilities', function () {
    $totem = makeTotemTemplate($this->admin, ['name' => 'Old']);
    $a1 = Action::factory()->create();
    $a2 = Action::factory()->create();
    $totem->campaignTotemActions()->sync([$a1->id => ['is_signature_action' => true]]);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.totem-templates.update', $totem->id), [
            'name' => 'New', 'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
            'campaign_is_black_joker_totem' => false,
            'campaign_is_red_joker_totem' => false,
            'campaign_totem_special_replace' => false,
            'action_ids' => [$a2->id],
            'signature_action_ids' => [],
        ])
        ->assertRedirect();

    expect($totem->fresh()->campaignTotemActions()->pluck('actions.id')->all())->toEqual([$a2->id]);
    expect($totem->fresh()->campaignTotemActions()->wherePivot('is_signature_action', true)->count())->toBe(0);
});

it('edit exposes base as the BaseSizeEnum-backed integer, not a string', function () {
    // Regression guard for a frontend bug: base is cast to BaseSizeEnum
    // (int-backed 30/40/50), so Inertia serializes it as a raw number. The
    // admin form must treat `item.base` as a number, not assume a string
    // (it previously called `.trim()` on it directly, which threw on any
    // edit of an existing template).
    $totem = makeTotemTemplate($this->admin, ['name' => 'Base Type Check', 'base' => '40']);

    $this->actingAs($this->admin)
        ->get(route('admin.campaign.totem-templates.edit', $totem->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campaign/TotemTemplate/Form')
            ->where('item.base', 40)
        );
});

it('delete removes the totem template', function () {
    $totem = makeTotemTemplate($this->admin);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.totem-templates.delete', $totem->id))
        ->assertRedirect();

    expect(CustomCharacter::find($totem->id))->toBeNull();
});
