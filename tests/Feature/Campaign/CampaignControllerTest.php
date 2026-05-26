<?php

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignPlayer;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function campaignUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

it('redirects anonymous campaigns index to login', function () {
    // Laravel middleware priority puts `Authenticate` before user-defined
    // middleware, so anonymous → 302 to login (not 404). Fine for /campaigns/*
    // which all require auth anyway. Feature-existence hiding via 404 matters
    // for the public invitation-accept screen, which lives outside the auth
    // group — covered in the next test.
    $this->get(route('campaigns.index'))->assertRedirect(route('login'));
});

it('returns 404 to anonymous on the public invitation screen when feature is off', function () {
    // No auth required for invitation.show, so EnsureCampaignAccess fires
    // first — anonymous + flag off + no permission → 404, hiding existence.
    $this->get('/campaigns/invitations/some-random-token-123')->assertNotFound();
});

it('returns 404 to authed users without use_campaign_mode (feature hidden)', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get(route('campaigns.index'))->assertNotFound();
});

it('shows the empty state for a permissioned user with no campaigns', function () {
    $user = campaignUser();

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/Index')
            ->where('campaigns', [])
        );
});

it('creates a campaign and auto-adds the organizer as a player', function () {
    $user = campaignUser();

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Test Saga',
            'length_weeks' => 6,
            'competitive' => false,
            'weekly_event_active' => false,
            'optional_rules' => ['extra_scrip' => true],
        ])
        ->assertRedirect();

    $campaign = Campaign::firstWhere('name', 'Test Saga');
    expect($campaign)->not->toBeNull();
    expect($campaign->organizer_user_id)->toBe($user->id);
    expect($campaign->status)->toBe(CampaignStatusEnum::Planning);
    expect($campaign->optional_rules)->toBe(['extra_scrip' => true]);

    $player = CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $user->id)->first();
    expect($player)->not->toBeNull();
    expect($player->role)->toBe(CampaignPlayerRoleEnum::Organizer);
});

it('rejects invalid length_weeks on store', function () {
    $this->actingAs(campaignUser())
        ->post(route('campaigns.store'), [
            'name' => 'Too Short',
            'length_weeks' => 1, // below min:2
            'competitive' => false,
            'weekly_event_active' => false,
        ])
        ->assertSessionHasErrors(['length_weeks']);
});

it('lists only campaigns the user is a member of', function () {
    $user = campaignUser();
    $other = campaignUser();

    // User's own campaign
    $mine = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $mine->id, 'user_id' => $user->id]);

    // Other user's campaign — user is NOT a member
    Campaign::factory()->create(['organizer_user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('campaigns.index'))
        ->assertInertia(fn ($page) => $page
            ->has('campaigns', 1)
            ->where('campaigns.0.name', $mine->name)
        );
});

it('blocks non-member from viewing a campaign', function () {
    $user = campaignUser();
    $organizer = campaignUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $organizer->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertForbidden();
});

it('shows a campaign to its members', function () {
    $user = campaignUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/Show')
            ->where('is_organizer', true)
        );
});

it('blocks settings access for non-organizer player', function () {
    $organizer = campaignUser();
    $player = campaignUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $organizer->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $player->id]);

    $this->actingAs($player)
        ->get(route('campaigns.settings', $campaign))
        ->assertForbidden();
});

it('blocks start until at least 2 players are present', function () {
    $user = campaignUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id, 'status' => CampaignStatusEnum::Planning]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.start', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe(CampaignStatusEnum::Planning);
});

it('starts the campaign with at least 2 players', function () {
    $organizer = campaignUser();
    $player = campaignUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $organizer->id, 'status' => CampaignStatusEnum::Planning]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $player->id]);

    $this->actingAs($organizer)
        ->post(route('campaigns.start', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatusEnum::Active);
    expect($campaign->started_at)->not->toBeNull();
});

it('ends an active campaign', function () {
    $user = campaignUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.end', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe(CampaignStatusEnum::Ended);
});
