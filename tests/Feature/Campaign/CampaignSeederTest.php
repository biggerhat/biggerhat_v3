<?php

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignGame;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Database\Seeders\CampaignSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Permissions must exist before the seeder can assign them.
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

it('creates two users with campaign permission', function () {
    (new CampaignSeeder)->run();

    $alpha = User::where('email', 'alpha@biggerhat.test')->first();
    $beta = User::where('email', 'beta@biggerhat.test')->first();

    expect($alpha)->not->toBeNull()
        ->and($beta)->not->toBeNull()
        ->and($alpha->hasPermissionTo(PermissionEnum::UseCampaignMode->value))->toBeTrue()
        ->and($beta->hasPermissionTo(PermissionEnum::UseCampaignMode->value))->toBeTrue();
});

it('creates an active campaign with two crews', function () {
    (new CampaignSeeder)->run();

    $campaign = Campaign::where('name', 'The Ashes of Malifaux')->first();
    expect($campaign)->not->toBeNull()
        ->and($campaign->status->value)->toBe('active')
        ->and($campaign->crews)->toHaveCount(2);
});

it('builds a current leader for each crew', function () {
    (new CampaignSeeder)->run();

    $campaign = Campaign::where('name', 'The Ashes of Malifaux')->with('crews.leader')->first();

    foreach ($campaign->crews as $crew) {
        $leader = $crew->leader;
        expect($leader)
            ->not->toBeNull()
            ->and($leader->is_campaign_leader)->toBeTrue()
            ->and($leader->current)->toBeTrue();
    }
});

it('seeds five arsenal models per crew', function () {
    (new CampaignSeeder)->run();

    $campaign = Campaign::where('name', 'The Ashes of Malifaux')->first();

    foreach ($campaign->crews as $crew) {
        expect(CampaignArsenalModel::where('campaign_crew_id', $crew->id)->count())->toBe(5);
    }
});

it('creates a live campaign game at MasterSelect', function () {
    (new CampaignSeeder)->run();

    $campaign = Campaign::where('name', 'The Ashes of Malifaux')->first();
    $campaignGame = CampaignGame::where('campaign_id', $campaign->id)->whereNotNull('base_game_id')->first();

    expect($campaignGame)->not->toBeNull();

    $game = Game::find($campaignGame->base_game_id);
    expect($game->status->value)->toBe('master_select')
        ->and($game->format->value)->toBe('campaign')
        ->and($game->players)->toHaveCount(2);
});

it('master select returns the seeded leader via campaign_leader_option for each player', function () {
    (new CampaignSeeder)->run();

    $campaign = Campaign::where('name', 'The Ashes of Malifaux')->first();
    $campaignGame = CampaignGame::where('campaign_id', $campaign->id)->whereNotNull('base_game_id')->first();
    $game = Game::find($campaignGame->base_game_id);

    foreach ([$campaignGame->crewA, $campaignGame->crewB] as $crew) {
        $user = User::find($crew->user_id);

        $response = test()->actingAs($user)
            ->get(route('games.show', $game->uuid))
            ->assertOk();

        // Campaign leader comes via campaign_leader_option (separate from catalog masters).
        // masters is [] for non-solo campaign games.
        $response->assertInertia(fn ($page) => $page
            ->where('masters', [])
            ->where('campaign_leader_option.name', $crew->leader->name)
        );
    }
});

it('solo campaign game returns leader and arsenal via faction fallback when no CampaignGame is linked', function () {
    (new CampaignSeeder)->run();

    $campaign = Campaign::where('name', 'The Ashes of Malifaux')->with('crews.leader')->first();
    $crew = $campaign->crews->first(); // Arcanists crew (alpha user)
    $user = User::find($crew->user_id);

    // Create a solo Campaign game via the standard tracker flow (no CampaignGame row).
    $game = Game::create([
        'format' => GameFormatEnum::Campaign->value,
        'status' => GameStatusEnum::MasterSelect->value,
        'season' => PoolSeasonEnum::cases()[0]->value,
        'creator_id' => $user->id,
        'encounter_size' => 50,
        'started_at' => now(),
        'is_solo' => true,
    ]);
    GamePlayer::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'slot' => 1,
        'faction' => $crew->faction->value,
    ]);

    // Leader shown at MasterSelect.
    test()->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_leader_option.name', $crew->leader->name)
        );

    // Arsenal shown at CrewSelect.
    $game->update(['status' => GameStatusEnum::CrewSelect->value]);

    test()->actingAs($user)
        ->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campaign_arsenal', fn ($arsenal) => count($arsenal) === 5)
        );

    // submitCampaignCrew accepts picks from the arsenal.
    $firstCharId = $crew->arsenalModels()->active()->value('character_id');
    test()->actingAs($user)
        ->postJson(route('games.setup.campaign-crew', $game->uuid), ['character_ids' => [$firstCharId]])
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('is idempotent on users — re-running does not duplicate users', function () {
    (new CampaignSeeder)->run();
    (new CampaignSeeder)->run();

    expect(User::where('email', 'alpha@biggerhat.test')->count())->toBe(1)
        ->and(User::where('email', 'beta@biggerhat.test')->count())->toBe(1);
    // Two campaign runs → two campaigns
    expect(Campaign::where('name', 'The Ashes of Malifaux')->count())->toBe(2);
});
