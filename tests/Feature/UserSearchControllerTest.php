<?php

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignInvitation;
use App\Models\Campaign\CampaignPlayer;
use App\Models\User;

it('finds users by name prefix', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    User::factory()->create(['name' => 'Alice Anderson']);
    User::factory()->create(['name' => 'Alicia Byrne']);
    User::factory()->create(['name' => 'Bob Carter']);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali')
        ->assertOk()
        ->assertJsonCount(2, 'users');
});

it('excludes the searching user from their own results', function () {
    $me = User::factory()->create(['name' => 'Alice Anderson', 'email_verified_at' => now()]);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali')
        ->assertOk()
        ->assertJsonCount(0, 'users');
});

it('returns nothing for a query shorter than 2 characters', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    User::factory()->create(['name' => 'Alice Anderson']);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=A')
        ->assertOk()
        ->assertJsonCount(0, 'users');
});

it('requires authentication', function () {
    $this->getJson(route('users.search').'?q=Alice')
        ->assertUnauthorized();
});

it('excludes users already a Campaign player when exclude_campaign_id is passed', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    $alreadyPlayer = User::factory()->create(['name' => 'Alice Anderson']);
    $stillEligible = User::factory()->create(['name' => 'Alicia Byrne']);

    $campaign = Campaign::factory()->create(['organizer_user_id' => $me->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $alreadyPlayer->id]);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali&exclude_campaign_id='.$campaign->id)
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonFragment(['name' => $stillEligible->name]);
});

it('excludes users with a pending Campaign invitation when exclude_campaign_id is passed', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    $alreadyInvited = User::factory()->create(['name' => 'Alice Anderson']);
    $expiredInvite = User::factory()->create(['name' => 'Alicia Byrne']);
    $stillEligible = User::factory()->create(['name' => 'Alicia Carter']);

    $campaign = Campaign::factory()->create(['organizer_user_id' => $me->id]);
    CampaignInvitation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $alreadyInvited->id, 'expires_at' => null]);
    CampaignInvitation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $expiredInvite->id, 'expires_at' => now()->subDay()]);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali&exclude_campaign_id='.$campaign->id)
        ->assertOk()
        ->assertJsonCount(2, 'users')
        ->assertJsonFragment(['name' => $stillEligible->name])
        ->assertJsonFragment(['name' => $expiredInvite->name]);
});

it('does not exclude anyone when exclude_campaign_id is omitted, even with Campaign rows present', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    $player = User::factory()->create(['name' => 'Alice Anderson']);

    $campaign = Campaign::factory()->create(['organizer_user_id' => $me->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $player->id]);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali')
        ->assertOk()
        ->assertJsonCount(1, 'users');
});
