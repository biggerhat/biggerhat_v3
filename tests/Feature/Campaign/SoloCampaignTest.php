<?php

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
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

function soloUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

it('creates a solo campaign with is_solo persisted', function () {
    $user = soloUser();

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Just Me',
            'length_weeks' => 4,
            'competitive' => false,
            'weekly_event_active' => false,
            'is_solo' => true,
        ])
        ->assertRedirect();

    $campaign = Campaign::where('name', 'Just Me')->firstOrFail();
    expect($campaign->is_solo)->toBeTrue()
        ->and($campaign->organizer_user_id)->toBe($user->id);
});

it('lets a solo campaign start with only 1 player', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Planning,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.start', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe(CampaignStatusEnum::Active);
});

it('still requires 2 players for non-solo campaigns', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Planning,
        'is_solo' => false,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.start', $campaign))
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe(CampaignStatusEnum::Planning);
});

it('refuses invitations on solo campaigns', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id, 'is_solo' => true]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.invitations.store', $campaign), ['email' => 'a@b.com'])
        ->assertForbidden();
});

it('refuses the multiplayer game-create endpoint on solo campaigns', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.store', $campaign), [
            'opponent_crew_id' => 999,
            'name' => 'should be blocked',
        ])
        ->assertNotFound();
});

it('logs a solo game and spawns an Aftermath', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
        'current_week' => 2,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'name' => 'Solo run #1',
            'vp_self' => 7,
            'vp_opponent' => 4,
            'schemes_completed' => 2,
            'won' => true,
            'withdrew' => false,
        ])
        ->assertRedirect();

    $game = CampaignGame::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($game->crew_a_id)->toBe($crew->id)
        ->and($game->crew_b_id)->toBeNull()
        ->and($game->base_game_id)->toBeNull()
        ->and($game->vp_a)->toBe(7)
        ->and($game->schemes_completed_a)->toBe(2)
        ->and($game->winner_crew_id)->toBe($crew->id)
        ->and((int) $game->week_number)->toBe(2)
        ->and($game->status)->toBe('aftermath');

    $aftermath = CampaignAftermath::query()
        ->where('campaign_game_id', $game->id)
        ->where('campaign_crew_id', $crew->id)
        ->first();
    expect($aftermath)->not->toBeNull()
        ->and((int) $aftermath->current_phase)->toBe(1)
        ->and($aftermath->status)->toBe('open');
});

it('rejects the solo log endpoint on non-solo campaigns', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => false,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => 1, 'schemes_completed' => 0, 'won' => false,
        ])
        ->assertNotFound();
});

it('validates solo game log input', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    // Negative VP rejected.
    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => -1, 'schemes_completed' => 0, 'won' => false,
        ])
        ->assertSessionHasErrors('vp_self');

    // schemes > 3 rejected.
    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => 5, 'schemes_completed' => 4, 'won' => false,
        ])
        ->assertSessionHasErrors('schemes_completed');
});

it('auto-creates the organizer crew on solo campaign creation', function () {
    $user = soloUser();

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Hermit Saga',
            'length_weeks' => 4,
            'competitive' => false,
            'weekly_event_active' => false,
            'is_solo' => true,
        ])
        ->assertRedirect();

    $campaign = Campaign::where('name', 'Hermit Saga')->firstOrFail();
    $crew = CampaignCrew::where('campaign_id', $campaign->id)
        ->where('user_id', $user->id)
        ->first();

    expect($crew)->not->toBeNull()
        ->and($crew->name)->toContain($user->name);
});

it('self-heals solo log when the organizer crew is missing', function () {
    // Simulates a campaign created before the auto-crew patch. The log
    // endpoints should mint the crew on demand instead of 404ing.
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    // Deliberately no CampaignCrew row.

    $this->actingAs($user)
        ->get(route('campaigns.games.log', $campaign))
        ->assertOk();

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('increments total_wins on a winning solo game (no GameObserver path)', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'total_wins' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => 6,
            'schemes_completed' => 1,
            'won' => true,
        ])
        ->assertRedirect();

    expect($crew->fresh()->total_wins)->toBe(1);

    // Losing follow-up game should not bump the counter.
    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => 2,
            'schemes_completed' => 0,
            'won' => false,
        ])
        ->assertRedirect();

    expect($crew->fresh()->total_wins)->toBe(1);
});

it('back-fills the organizer crew on Show for legacy solo campaigns', function () {
    // Predates the auto-crew patch in store().
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk();

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('requires withdrew_turn when withdrew is true', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->solo()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => 5, 'schemes_completed' => 1, 'won' => false,
            'withdrew' => true,
            // withdrew_turn intentionally omitted.
        ])
        ->assertSessionHasErrors('withdrew_turn');
});

it('blocks the Log Game form GET when campaign is not active', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->solo()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Planning,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('campaigns.games.log', $campaign))
        ->assertRedirect(route('campaigns.show', $campaign));
});

it('refuses solo log when campaign is not active', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Planning,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.log.store', $campaign), [
            'vp_self' => 5, 'schemes_completed' => 1, 'won' => true,
        ])
        ->assertRedirect();

    expect(CampaignGame::query()->where('campaign_id', $campaign->id)->count())->toBe(0);
});

// ─── Play Live (live Game Tracker session for solo campaigns) ───

it('playLive starts a live game with an eagerly-linked CampaignGame row', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
        'current_week' => 3,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'faction' => \App\Enums\FactionEnum::Arcanists->value,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.games.play', $campaign))
        ->assertRedirect();

    $game = \App\Models\Game::query()->where('creator_id', $user->id)->latest('id')->firstOrFail();
    expect($game->format)->toBe(\App\Enums\GameFormatEnum::Campaign)
        ->and($game->is_solo)->toBeTrue()
        ->and($game->status)->toBe(\App\Enums\GameStatusEnum::MasterSelect);

    $players = $game->players()->orderBy('slot')->get();
    expect($players)->toHaveCount(2)
        ->and($players[0]->user_id)->toBe($user->id)
        ->and($players[0]->getRawOriginal('faction'))->toBe($crew->getRawOriginal('faction'))
        ->and($players[1]->user_id)->toBeNull()
        ->and($players[1]->getRawOriginal('faction'))->toBe($crew->getRawOriginal('faction'));

    $wrap = CampaignGame::query()->where('base_game_id', $game->id)->firstOrFail();
    expect($wrap->campaign_id)->toBe($campaign->id)
        ->and($wrap->crew_a_id)->toBe($crew->id)
        ->and($wrap->crew_b_id)->toBeNull()
        ->and((int) $wrap->week_number)->toBe(3);
});

it('Campaigns/Show exposes active_solo_game once Play Live has been clicked, so the hub can offer Resume', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'faction' => \App\Enums\FactionEnum::Arcanists->value,
    ]);

    // No game yet — hub should not offer a resume option.
    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('active_solo_game', null));

    $this->actingAs($user)->post(route('campaigns.games.play', $campaign))->assertRedirect();
    $game = \App\Models\Game::query()->where('creator_id', $user->id)->latest('id')->firstOrFail();

    // Not finished yet — hub should surface it for Resume.
    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('active_solo_game.uuid', $game->uuid)
            ->where('active_solo_game.status', 'master_select')
        );

    // Once finished, it's no longer "active" — a fresh Play Live click should
    // mint a new game rather than offering to resume a completed one.
    $game->update(['status' => \App\Enums\GameStatusEnum::Completed->value]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('active_solo_game', null));
});

it('playLive 404s on a non-solo campaign', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => false,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.play', $campaign))
        ->assertNotFound();
});

it('playLive refuses when the campaign is not active', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Planning,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('campaigns.games.play', $campaign))
        ->assertRedirect();

    expect(\App\Models\Game::query()->where('creator_id', $user->id)->count())->toBe(0);
});

it('playLive self-heals the organizer crew when missing', function () {
    $user = soloUser();
    $campaign = Campaign::factory()->create([
        'organizer_user_id' => $user->id,
        'status' => CampaignStatusEnum::Active,
        'is_solo' => true,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    // Deliberately no CampaignCrew row.

    $this->actingAs($user)
        ->post(route('campaigns.games.play', $campaign))
        ->assertRedirect();

    expect(CampaignCrew::where('campaign_id', $campaign->id)->where('user_id', $user->id)->exists())->toBeTrue();
});
