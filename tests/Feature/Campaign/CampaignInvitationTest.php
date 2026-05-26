<?php

use App\Enums\CampaignStatusEnum;
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
