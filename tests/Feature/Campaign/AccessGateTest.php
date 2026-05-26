<?php

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Register a tiny gated route so we can probe the middleware directly,
    // before any real campaign routes exist.
    Route::middleware(['web', 'campaign.access'])
        ->get('/__test/campaign-gate', fn () => response('ok', 200));

    // PermissionSeeder normally provisions these; for in-memory SQLite
    // RefreshDatabase, seed the bare minimum the gate needs.
    Permission::firstOrCreate(['name' => PermissionEnum::UseCampaignMode->value]);
    Role::firstOrCreate(['name' => 'super_admin']);
});

it('returns 404 to anonymous visitors when the feature flag is off', function () {
    $this->get('/__test/campaign-gate')->assertNotFound();
});

it('returns 404 to authed users without permission when the flag is off', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/__test/campaign-gate')
        ->assertNotFound();
});

it('lets a user with use_campaign_mode through while the flag is off', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $this->actingAs($user)
        ->get('/__test/campaign-gate')
        ->assertOk()
        ->assertSee('ok');
});

it('lets a super_admin through while the flag is off', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get('/__test/campaign-gate')
        ->assertOk();
});

it('lets anyone through when the feature flag is on globally', function () {
    Feature::for(null)->activate('m4e-campaign-mode');

    // Anonymous
    $this->get('/__test/campaign-gate')->assertOk();

    // Regular user, no permission
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/__test/campaign-gate')
        ->assertOk();
});

it('shows the campaign teaser to authed users without campaign access', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('campaigns.preview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Campaigns/Teaser'));
});

it('shows the campaign teaser to anonymous visitors too (no auth required)', function () {
    // Teaser is intentionally outside the campaign.access middleware so
    // unauthenticated traffic can land on it from external marketing.
    $this->get(route('campaigns.preview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Campaigns/Teaser'));
});

it('redirects users with campaign access from the teaser to the live index', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $this->actingAs($user)
        ->get(route('campaigns.preview'))
        ->assertRedirect(route('campaigns.index'));
});
