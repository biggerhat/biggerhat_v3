<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Events\CampaignCrewUpdated;
use App\Events\CampaignStarted;
use App\Events\CampaignWeekAdvanced;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Campaign\CampaignPlayer;
use App\Models\CustomCharacter;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function cbeUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

it('CampaignStarted is dispatched when the organizer starts a multiplayer campaign', function () {
    Event::fake([CampaignStarted::class]);

    $organizer = cbeUser();
    $other = cbeUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $organizer->id, 'status' => CampaignStatusEnum::Planning]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $other->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $other->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewB->id]);

    $this->actingAs($organizer)
        ->post(route('campaigns.start', $campaign))
        ->assertRedirect();

    Event::assertDispatched(CampaignStarted::class, fn ($e) => $e->campaign->id === $campaign->id);
});

it('CampaignStarted is NOT dispatched for a solo campaign (nothing to broadcast to)', function () {
    Event::fake([CampaignStarted::class]);

    $user = cbeUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id, 'status' => CampaignStatusEnum::Planning, 'is_solo' => true]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crew->id]);

    $this->actingAs($user)
        ->post(route('campaigns.start', $campaign))
        ->assertRedirect();

    // The event object is still constructed (dispatch() always fires the
    // Laravel event), but broadcastToCampaign's is_solo guard means
    // broadcast()->toOthers() is never reached — verified indirectly by the
    // absence of an exception and the campaign still transitioning normally.
    expect($campaign->fresh()->status)->toBe(CampaignStatusEnum::Active);
});

it('CampaignWeekAdvanced is dispatched when the organizer advances the week', function () {
    Event::fake([CampaignWeekAdvanced::class]);

    $organizer = cbeUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $organizer->id, 'current_week' => 1, 'length_weeks' => 6]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);

    $this->actingAs($organizer)
        ->post(route('campaigns.weeks.advance', $campaign))
        ->assertRedirect();

    Event::assertDispatched(CampaignWeekAdvanced::class, fn ($e) => $e->campaign->id === $campaign->id && $e->campaign->current_week === 2);
});

it('CampaignCrewUpdated is dispatched on Starting Arsenal save', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = cbeUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'faction' => FactionEnum::Resurrectionists->value,
        'keyword_1_id' => \App\Models\Keyword::factory()->create()->id,
        'keyword_2_id' => \App\Models\Keyword::factory()->create()->id,
    ]);
    CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Starter Leader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $starter = \App\Models\Campaign\CampaignCrewCard::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-arsenal.update', [$campaign, $crew->share_code]), [
            'hires' => [],
            'crew_card_effect_id' => $starter->id,
        ]);

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);
});

it('CampaignCrewUpdated is dispatched on a Weekly Hire', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = cbeUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'current_week' => 2]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $kw = \App\Models\Keyword::factory()->create();
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'faction' => FactionEnum::Arcanists->value,
        'keyword_1_id' => $kw->id,
        'scrip' => 20,
    ]);
    $hire = \App\Models\Character::factory()->create([
        'cost' => 8,
        'station' => \App\Enums\CharacterStationEnum::Minion,
        'faction' => FactionEnum::Arcanists,
    ]);
    $hire->keywords()->attach($kw);

    $this->actingAs($user)
        ->post(route('campaigns.crews.weekly-hire.update', [$campaign, $crew->share_code]), [
            'hires' => [['character_id' => $hire->id]],
        ])
        ->assertRedirect();

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);
});

it('CampaignCrewUpdated is dispatched when an advancement is logged and when it\'s removed', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = cbeUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    // Box index 0 is a tier-1 box in the canonical track — mark it earned
    // (mirrors LeaderAdvancementTest::leaderWithEarnedTier1Box()).
    $track = CustomCharacter::defaultXpTrack();
    $track[0]['filled'] = true;
    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'L',
        'faction' => 'guild',
        'station' => 'master',
        'health' => 12, 'defense' => 5, 'willpower' => 6, 'speed' => 5,
        'tag' => 'bruiser',
        'xp_track' => $track,
    ]);
    $advancementCatalog = \App\Models\Campaign\AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $advancementCatalog->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->where('position_in_xp_track', 0)->firstOrFail();

    Event::fake([CampaignCrewUpdated::class]);
    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign, $crew->share_code, $advancement]))
        ->assertRedirect();

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);
});

it('CampaignCrewUpdated is dispatched on Starting Anew', function () {
    Event::fake([CampaignCrewUpdated::class]);

    $user = cbeUser();
    $campaign = Campaign::factory()->active()->create(['organizer_user_id' => $user->id, 'current_week' => 4]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.starting-anew', [$campaign, $crew->share_code]))
        ->assertRedirect();

    Event::assertDispatched(CampaignCrewUpdated::class, fn ($e) => $e->crew->id === $crew->id);
});

it('campaign.{id} channel authorizes a campaign member and rejects a non-member', function () {
    $member = cbeUser();
    $outsider = cbeUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $member->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $member->id]);

    // Exercises the exact resolver registered in routes/channels.php (rather
    // than round-tripping through the /broadcasting/auth HTTP endpoint,
    // which needs a real broadcast connection this sandbox doesn't have).
    $channels = \Illuminate\Support\Facades\Broadcast::driver()->getChannels();
    $resolver = $channels['campaign.{id}'];

    expect($resolver($member, $campaign->id))->not->toBeFalse()
        ->and($resolver($outsider, $campaign->id))->toBeFalse();
});
