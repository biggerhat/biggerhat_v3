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
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function cintUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function campaignGameSetup(): array
{
    $userA = cintUser();
    $userB = cintUser();
    $campaign = Campaign::factory()->active()->create([
        'organizer_user_id' => $userA->id,
        'current_week' => 2,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id, 'faction' => FactionEnum::Arcanists->value]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id, 'faction' => FactionEnum::Guild->value]);
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Campaign->value,
        'status' => GameStatusEnum::CrewSelect->value,
        'creator_id' => $userA->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $userA->id, 'slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $userB->id, 'slot' => 2]);
    CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crewA->id,
        'crew_b_id' => $crewB->id,
        'base_game_id' => $game->id,
        'cr_a' => 1,
        'cr_b' => -2,
        'ss_bonus_to_lower' => 3,
        'encounter_size' => 50,
        'week_number' => 2,
    ]);

    return [$userA, $userB, $campaign, $crewA, $crewB, $game];
}

// ───── /games/{uuid} campaign banner ─────

it('Games/Show payload includes campaign_context when format=Campaign', function () {
    [$userA, , $campaign, $crewA] = campaignGameSetup();
    $game = Game::first();

    $this->actingAs($userA)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Show')
            ->where('campaign_context.campaign.name', $campaign->name)
            ->where('campaign_context.crew_a.id', $crewA->id)
            ->where('campaign_context.cr_a', 1)
            ->where('campaign_context.cr_b', -2)
            ->where('campaign_context.ss_bonus_to_lower', 3)
        );
});

it('Games/Show payload campaign_context is null for non-campaign games', function () {
    $user = cintUser();
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Standard->value,
        'status' => GameStatusEnum::InProgress->value,
        'creator_id' => $user->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('campaign_context', null));
});

// ───── Arsenal-constraint enforcement at crew select ─────

it('submitCrew rejects characters not in the player crew arsenal', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    // Stock the arsenal with one character only.
    $allowed = Character::factory()->create(['cost' => 6]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $allowed->id]);

    // Build a crew including an outside character.
    $forbidden = Character::factory()->create(['cost' => 7, 'name' => 'Disallowed Model']);
    $master = Character::factory()->create();
    $build = CrewBuild::create([
        'user_id' => $userA->id,
        'master_id' => $master->id,
        'name' => 'Test',
        'faction' => FactionEnum::Arcanists->value,
        'crew_data' => [$allowed->id, $forbidden->id],
    ]);

    $this->actingAs($userA)
        ->postJson(route('games.setup.crew', $game->uuid), [
            'crew_build_id' => $build->id,
            'slot' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Campaign games must hire from your arsenal — these are not in it: Disallowed Model']);
});

it('submitCrew accepts a crew composed entirely of arsenal characters', function () {
    [$userA, , , $crewA, , $game] = campaignGameSetup();

    $char = Character::factory()->create(['cost' => 6]);
    CampaignArsenalModel::factory()->create(['campaign_crew_id' => $crewA->id, 'character_id' => $char->id]);

    $master = Character::factory()->create();
    $build = CrewBuild::create([
        'user_id' => $userA->id,
        'master_id' => $master->id,
        'name' => 'Legit Crew',
        'faction' => FactionEnum::Arcanists->value,
        'crew_data' => [$char->id],
    ]);

    // Set up master select state expected by submitCrew.
    $this->actingAs($userA)
        ->postJson(route('games.setup.crew', $game->uuid), [
            'crew_build_id' => $build->id,
            'slot' => 1,
        ])
        ->assertOk();
});

it('submitCrew arsenal check is a no-op for non-campaign games', function () {
    $user = cintUser();
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Standard->value,
        'status' => GameStatusEnum::CrewSelect->value,
        'creator_id' => $user->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $char = Character::factory()->create();
    $master = Character::factory()->create();
    $build = CrewBuild::create([
        'user_id' => $user->id,
        'master_id' => $master->id,
        'name' => 'Standard Crew',
        'faction' => FactionEnum::Guild->value,
        'crew_data' => [$char->id],
    ]);

    $this->actingAs($user)
        ->postJson(route('games.setup.crew', $game->uuid), [
            'crew_build_id' => $build->id,
            'slot' => 1,
        ])
        ->assertOk();
});
