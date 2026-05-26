<?php

use App\Enums\LeaderArchetypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\LeaderArchetype;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function adminWithCampaignPerms(): User
{
    $user = User::factory()->create();
    // Admin-area entry: needs ANY view_* permission. Plus the catalog perms
    // we're exercising. The campaign.access gate also requires the feature
    // flag or use_campaign_mode permission.
    $user->givePermissionTo([
        PermissionEnum::ViewCampaignCatalog->value,
        PermissionEnum::EditCampaignCatalog->value,
        PermissionEnum::DeleteCampaignCatalog->value,
        PermissionEnum::UseCampaignMode->value,
    ]);
    $user->email_verified_at = now();
    $user->save();

    return $user;
}

it('redirects anonymous visitors to login (auth runs before campaign.access)', function () {
    // 404 feature-hiding is for public routes; admin routes correctly bounce
    // unauthenticated users to login. The `auth` middleware fires first in
    // the admin stack, so a campaign.access 404 only kicks in for authed-but-
    // ungated users (covered by separate tests below).
    $this->get(route('admin.campaign.leader-archetypes.index'))
        ->assertRedirect(route('login'));
});

it('returns 404 to authed users who fail the campaign.access gate', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    // Has view_campaign_catalog but NOT use_campaign_mode → admin.any passes
    // because view_campaign_catalog grants admin-area entry, but campaign.access
    // fails because the feature flag is off and the user lacks use_campaign_mode.
    $user->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($user)
        ->get(route('admin.campaign.leader-archetypes.index'))
        ->assertNotFound();
});

it('rejects authed users without permission with 403 from the admin gate', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('admin.campaign.leader-archetypes.index'))
        ->assertForbidden();
});

it('renders the index for users with view_campaign_catalog', function () {
    $admin = adminWithCampaignPerms();
    LeaderArchetype::factory()->create(['name' => 'Generalist']);

    $this->actingAs($admin)
        ->get(route('admin.campaign.leader-archetypes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Campaign/LeaderArchetype/Index')
            ->has('archetypes', 1)
            ->where('archetypes.0.name', 'Generalist')
        );
});

it('creates an archetype on store happy path', function () {
    $admin = adminWithCampaignPerms();

    $this->actingAs($admin)
        ->post(route('admin.campaign.leader-archetypes.store'), [
            'slug' => LeaderArchetypeEnum::HeavyHitter->value,
            'name' => 'Heavy Hitter',
            'df' => 6, 'wp' => 4, 'sp' => 6, 'health' => 14,
            'attack_actions_count' => 1, 'attack_action_cost_cap' => 10, 'attack_gets_trigger' => true,
            'tactical_actions_count' => 1, 'tactical_action_cost_cap' => 5,
            'abilities_count' => 0, 'ability_cost_cap' => 0,
        ])
        ->assertRedirect(route('admin.campaign.leader-archetypes.index'));

    expect(LeaderArchetype::where('slug', 'heavy_hitter')->exists())->toBeTrue();
});

it('rejects invalid archetype slug on store', function () {
    $admin = adminWithCampaignPerms();

    $this->actingAs($admin)
        ->post(route('admin.campaign.leader-archetypes.store'), [
            'slug' => 'not_a_real_archetype',
            'name' => 'Bogus',
            'df' => 5, 'wp' => 5, 'sp' => 5, 'health' => 10,
            'attack_actions_count' => 1, 'attack_action_cost_cap' => 5, 'attack_gets_trigger' => false,
            'tactical_actions_count' => 1, 'tactical_action_cost_cap' => 5,
            'abilities_count' => 1, 'ability_cost_cap' => 5,
        ])
        ->assertSessionHasErrors(['slug']);
});

it('rejects store without edit_campaign_catalog', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo([
        PermissionEnum::ViewCampaignCatalog->value,
        PermissionEnum::UseCampaignMode->value,
    ]);

    $this->actingAs($user)
        ->post(route('admin.campaign.leader-archetypes.store'), [
            'slug' => LeaderArchetypeEnum::Schemer->value,
            'name' => 'Schemer',
            'df' => 6, 'wp' => 5, 'sp' => 7, 'health' => 13,
            'attack_actions_count' => 1, 'attack_action_cost_cap' => 5, 'attack_gets_trigger' => false,
            'tactical_actions_count' => 2, 'tactical_action_cost_cap' => 8,
            'abilities_count' => 1, 'ability_cost_cap' => 8,
        ])
        ->assertForbidden();
});

it('updates an archetype on happy path', function () {
    $admin = adminWithCampaignPerms();
    $row = LeaderArchetype::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.campaign.leader-archetypes.update', $row->slug), [
            'slug' => $row->slug,
            'name' => 'Renamed',
            'df' => $row->df, 'wp' => $row->wp, 'sp' => $row->sp, 'health' => $row->health,
            'attack_actions_count' => $row->attack_actions_count,
            'attack_action_cost_cap' => $row->attack_action_cost_cap,
            'attack_gets_trigger' => $row->attack_gets_trigger,
            'tactical_actions_count' => $row->tactical_actions_count,
            'tactical_action_cost_cap' => $row->tactical_action_cost_cap,
            'abilities_count' => $row->abilities_count,
            'ability_cost_cap' => $row->ability_cost_cap,
        ])
        ->assertRedirect();

    expect($row->fresh()->name)->toBe('Renamed');
});

it('deletes an archetype with delete_campaign_catalog', function () {
    $admin = adminWithCampaignPerms();
    $row = LeaderArchetype::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.campaign.leader-archetypes.delete', $row->slug))
        ->assertRedirect();

    expect(LeaderArchetype::find($row->id))->toBeNull();
});

it('rejects delete without delete_campaign_catalog', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo([
        PermissionEnum::ViewCampaignCatalog->value,
        PermissionEnum::UseCampaignMode->value,
    ]);
    $row = LeaderArchetype::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.campaign.leader-archetypes.delete', $row->slug))
        ->assertForbidden();

    expect(LeaderArchetype::find($row->id))->not->toBeNull();
});
