<?php

use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;
use App\Notifications\Campaign\CampaignGameStarted;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());

    Strategy::factory()->count(3)->create();
    Scheme::factory()->count(5)->create();
});

function cgUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

/** @return array{Campaign, CampaignCrew, CampaignCrew, User, User} */
function twoCrewCampaign(): array
{
    $organizer = cgUser();
    $opponent = cgUser();
    $campaign = Campaign::factory()->active()->create([
        'organizer_user_id' => $organizer->id,
        'current_week' => 2,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $opponent->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $organizer->id, 'faction' => FactionEnum::Arcanists->value]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $opponent->id, 'faction' => FactionEnum::Guild->value]);

    return [$campaign, $crewA, $crewB, $organizer, $opponent];
}

it('renders the new-game form for a campaign member', function () {
    [$campaign, , , $organizer] = twoCrewCampaign();

    $this->actingAs($organizer)
        ->get(route('campaigns.games.create', $campaign))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Campaigns/NewGame')
            ->has('opponents', 1)
        );
});

it('blocks non-members from the new-game form', function () {
    [$campaign] = twoCrewCampaign();
    $outsider = cgUser();

    $this->actingAs($outsider)
        ->get(route('campaigns.games.create', $campaign))
        ->assertForbidden();
});

it('creates a Game with format=Campaign + a wrapping campaign_games row', function () {
    [$campaign, $crewA, $crewB, $organizer] = twoCrewCampaign();

    // Seed arsenals so encounter size has something to derive from.
    $charA = Character::factory()->create(['cost' => 10]);
    $charB = Character::factory()->create(['cost' => 14]);
    CampaignArsenalModel::factory()->count(3)->create(['campaign_crew_id' => $crewA->id, 'character_id' => $charA->id]);
    CampaignArsenalModel::factory()->count(2)->create(['campaign_crew_id' => $crewB->id, 'character_id' => $charB->id]);

    $this->actingAs($organizer)
        ->post(route('campaigns.games.store', $campaign), [
            'opponent_crew_id' => $crewB->id,
            'name' => 'Sat night',
        ])
        ->assertRedirect();

    $game = Game::first();
    expect($game)->not->toBeNull();
    expect($game->format)->toBe(GameFormatEnum::Campaign);
    // Both players are pre-seeded at creation, so campaign games skip Setup
    // (waiting for join) and FactionSelect (faction comes from crew) and land
    // directly at MasterSelect with started_at already populated.
    expect($game->status)->toBe(GameStatusEnum::MasterSelect);
    expect($game->started_at)->not->toBeNull();
    expect($game->creator_id)->toBe($organizer->id);

    // Both players seeded as GamePlayer rows in slots 1/2 with factions and roles.
    $players = GamePlayer::where('game_id', $game->id)->orderBy('slot')->get();
    expect($players)->toHaveCount(2);
    expect($players[0]->user_id)->toBe($organizer->id);
    expect($players[0]->faction)->not->toBeNull();
    expect($players[0]->role)->not->toBeNull();
    expect($players[1]->user_id)->toBe($crewB->user_id);
    expect($players[1]->faction)->not->toBeNull();
    expect($players[1]->role)->not->toBeNull();

    // Wrapping campaign_games row exists.
    $wrap = CampaignGame::firstWhere('base_game_id', $game->id);
    expect($wrap)->not->toBeNull();
    expect($wrap->crew_a_id)->toBe($crewA->id);
    expect($wrap->crew_b_id)->toBe($crewB->id);
    expect($wrap->week_number)->toBe(2);
    // arsenal A = 3 * 10 = 30, B = 2 * 14 = 28; min + 6 = 34.
    expect($wrap->encounter_size)->toBe(34);
    expect($game->encounter_size)->toBe(34);
});

it('refuses to start a game against an opponent in a different campaign', function () {
    [$campaignA, , , $organizer] = twoCrewCampaign();
    [, , $crewBOther] = twoCrewCampaign(); // different campaign

    $this->actingAs($organizer)
        ->post(route('campaigns.games.store', $campaignA), [
            'opponent_crew_id' => $crewBOther->id,
        ])
        ->assertSessionHasErrors('opponent_crew_id');
});

it('refuses to play against yourself', function () {
    [$campaign, $crewA, , $organizer] = twoCrewCampaign();

    $this->actingAs($organizer)
        ->post(route('campaigns.games.store', $campaign), [
            'opponent_crew_id' => $crewA->id,
        ])
        ->assertStatus(422);

    expect(Game::count())->toBe(0);
});

it('refuses to start a game on a non-active campaign', function () {
    [$campaign, , $crewB, $organizer] = twoCrewCampaign();
    $campaign->update(['status' => \App\Enums\Campaign\CampaignStatusEnum::Planning]);

    $this->actingAs($organizer)
        ->post(route('campaigns.games.store', $campaign), [
            'opponent_crew_id' => $crewB->id,
        ])
        ->assertRedirect();

    expect(Game::count())->toBe(0);
});

it('notifies the opponent when a campaign game is started against their crew', function () {
    Notification::fake();
    [$campaign, , $crewB, $organizer, $opponent] = twoCrewCampaign();

    $this->actingAs($organizer)
        ->post(route('campaigns.games.store', $campaign), [
            'opponent_crew_id' => $crewB->id,
        ])
        ->assertRedirect();

    Notification::assertSentTo($opponent, CampaignGameStarted::class);
});
