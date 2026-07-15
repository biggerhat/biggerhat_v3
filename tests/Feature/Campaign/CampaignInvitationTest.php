<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignInvitation;
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

function invUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function campaignWithOrganizer(User $organizer): Campaign
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $organizer->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);

    return $campaign;
}

it('lets the organizer send an email invitation', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($organizer)
        ->post(route('campaigns.invitations.store', $campaign), [
            'email' => 'invitee@example.com',
            'expires_in_days' => 7,
        ])
        ->assertRedirect();

    $invitation = $campaign->invitations()->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->email)->toBe('invitee@example.com');
    expect($invitation->token)->not->toBeEmpty();
    expect($invitation->expires_at)->not->toBeNull();
});

it('notifies an existing user invited by user_id', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($organizer)
        ->post(route('campaigns.invitations.store', $campaign), ['user_id' => $invitee->id]);

    \Illuminate\Support\Facades\Notification::assertSentTo($invitee, \App\Notifications\Campaign\CampaignInvitationReceived::class);
});

it('does not notify anyone for an email-only invite (no User row to notify)', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($organizer)
        ->post(route('campaigns.invitations.store', $campaign), ['email' => 'nobody@example.com']);

    \Illuminate\Support\Facades\Notification::assertNothingSent();
});

it('blocks non-organizers from inviting', function () {
    $organizer = invUser();
    $other = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($other)
        ->post(route('campaigns.invitations.store', $campaign), [
            'email' => 'someone@example.com',
        ])
        ->assertForbidden();
});

it('renders the accept screen for the invitee', function () {
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($invitee)
        ->get(route('campaigns.invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/Invitations/Show')
            ->where('invitation.token', $invitation->token)
            ->where('invitation.campaign.name', $campaign->name)
        );
});

it('refuses to show an invitation keyed to a different user', function () {
    $organizer = invUser();
    $invitee = invUser();
    $stranger = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($stranger)
        ->get(route('campaigns.invitations.show', $invitation->token))
        ->assertForbidden();
});

it('shows expired screen for expired invitations', function () {
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->expired()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($invitee)
        ->get(route('campaigns.invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Campaigns/Invitations/Expired'));
});

it('accept creates the player membership AND a stub crew', function () {
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($invitee)
        ->post(route('campaigns.invitations.accept', $invitation->token))
        ->assertRedirect();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();

    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $invitee->id)->exists())->toBeTrue();
    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $invitee->id)->exists())->toBeTrue();
});

it('accept is idempotent on duplicate invitation tokens', function () {
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($invitee)->post(route('campaigns.invitations.accept', $invitation->token));
    // Second hit on the already-accepted invitation should not create a second crew.
    $this->actingAs($invitee)->post(route('campaigns.invitations.accept', $invitation->token));

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $invitee->id)->count())->toBe(1);
});

it('refuses accept on ended campaigns', function () {
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $campaign->update(['status' => CampaignStatusEnum::Ended]);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($invitee)
        ->post(route('campaigns.invitations.accept', $invitation->token))
        ->assertRedirect();

    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $invitee->id)->exists())->toBeFalse();
});

it('lets the organizer revoke a pending invitation', function () {
    $organizer = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($organizer)
        ->post(route('campaigns.invitations.revoke', [$campaign, $invitation]))
        ->assertRedirect();

    expect(CampaignInvitation::find($invitation->id))->toBeNull();
});

it('blocks non-organizer from revoking', function () {
    $organizer = invUser();
    $other = invUser();
    $invitee = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $invitation = CampaignInvitation::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $invitee->id,
    ]);

    $this->actingAs($other)
        ->post(route('campaigns.invitations.revoke', [$campaign, $invitation]))
        ->assertForbidden();
});

// ─── Public join link (reusable, not single-use) ───

it('joinPublic creates membership + stub crew for a new visitor', function () {
    $organizer = invUser();
    $visitor = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($visitor)
        ->get(route('campaigns.join', $campaign->uuid))
        ->assertRedirect(route('campaigns.show', $campaign));

    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $visitor->id)->exists())->toBeTrue();
    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $visitor->id)->exists())->toBeTrue();
});

it('joinPublic is idempotent for an already-existing member', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($organizer)
        ->get(route('campaigns.join', $campaign->uuid))
        ->assertRedirect(route('campaigns.show', $campaign));

    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->count())->toBe(1);
});

it('joinPublic rejects solo campaigns', function () {
    $organizer = invUser();
    $visitor = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $campaign->update(['is_solo' => true]);

    $this->actingAs($visitor)
        ->get(route('campaigns.join', $campaign->uuid))
        ->assertRedirect();

    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $visitor->id)->exists())->toBeFalse();
});

it('joinPublic rejects ended campaigns', function () {
    $organizer = invUser();
    $visitor = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $campaign->update(['status' => CampaignStatusEnum::Ended]);

    $this->actingAs($visitor)
        ->get(route('campaigns.join', $campaign->uuid))
        ->assertRedirect();

    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $visitor->id)->exists())->toBeFalse();
});

it('joinPublic bounces an unauthenticated visitor to login and back', function () {
    // Anonymous requests only pass the campaign.access gate at all once the
    // feature is globally live (CampaignAccess::canUse(null) is otherwise
    // always false pre-launch) — same as the existing invitation-accept
    // screen's anonymous behavior (see AccessGateTest.php).
    \Laravel\Pennant\Feature::for(null)->activate('m4e-campaign-mode');

    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->get(route('campaigns.join', $campaign->uuid))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('campaigns.join', $campaign->uuid));
});

it('joinAsPlayer backfills a crew for the organizer of a multiplayer campaign, who has no other path to one', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->exists())->toBeFalse();

    $this->actingAs($organizer)
        ->post(route('campaigns.join-as-player', $campaign))
        ->assertRedirect(route('campaigns.show', $campaign));

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->exists())->toBeTrue();
    // Doesn't create a second CampaignPlayer row — they already have one.
    expect(CampaignPlayer::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->count())->toBe(1);
});

it('joinAsPlayer is idempotent — does not create a second crew', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($organizer)->post(route('campaigns.join-as-player', $campaign))->assertRedirect();
    $this->actingAs($organizer)->post(route('campaigns.join-as-player', $campaign))->assertRedirect();

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->count())->toBe(1);
});

it('joinAsPlayer rejects a non-member', function () {
    $organizer = invUser();
    $stranger = invUser();
    $campaign = campaignWithOrganizer($organizer);

    $this->actingAs($stranger)
        ->post(route('campaigns.join-as-player', $campaign))
        ->assertStatus(403);

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $stranger->id)->exists())->toBeFalse();
});

it('joinAsPlayer rejects solo campaigns — the organizer already has a crew there', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $campaign->update(['is_solo' => true]);

    $this->actingAs($organizer)
        ->post(route('campaigns.join-as-player', $campaign))
        ->assertRedirect();

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->exists())->toBeFalse();
});

it('joinAsPlayer rejects ended campaigns', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $campaign->update(['status' => CampaignStatusEnum::Ended]);

    $this->actingAs($organizer)
        ->post(route('campaigns.join-as-player', $campaign))
        ->assertRedirect();

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $organizer->id)->exists())->toBeFalse();
});

it('regenerateJoinLink changes the uuid and invalidates the old link', function () {
    $organizer = invUser();
    $visitor = invUser();
    $campaign = campaignWithOrganizer($organizer);
    $oldUuid = $campaign->uuid;

    $this->actingAs($organizer)
        ->post(route('campaigns.join-link.regenerate', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->uuid)->not->toBe($oldUuid);

    $this->actingAs($visitor)
        ->get(route('campaigns.join', $oldUuid))
        ->assertNotFound();
});

it('blocks non-organizer from regenerating the join link', function () {
    $organizer = invUser();
    $other = invUser();
    $campaign = campaignWithOrganizer($organizer);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $other->id]);

    $this->actingAs($other)
        ->post(route('campaigns.join-link.regenerate', $campaign))
        ->assertForbidden();
});

it('lazily backfills uuid for a pre-existing campaign on show', function () {
    $organizer = invUser();
    $campaign = campaignWithOrganizer($organizer);
    // Simulate a campaign created before the uuid column existed.
    $campaign->update(['uuid' => null]);
    expect($campaign->fresh()->uuid)->toBeNull();

    $this->actingAs($organizer)
        ->get(route('campaigns.show', $campaign))
        ->assertOk();

    expect($campaign->fresh()->uuid)->not->toBeNull();
});
