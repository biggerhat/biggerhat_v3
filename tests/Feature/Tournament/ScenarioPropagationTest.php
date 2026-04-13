<?php

use App\Enums\PermissionEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Services\TournamentTrackerGameFactory;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);
});

it('pushes a newly-set scenario down to existing tracker games', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->active()->create(['creator_id' => $creator->id]);

    // Two players, one of whom has a BiggerHat user (so a tracker game gets created).
    $p1 = TournamentPlayer::factory()->for($tournament)->create(['user_id' => $creator->id]);
    $p2 = TournamentPlayer::factory()->for($tournament)->create();
    $round = TournamentRound::factory()->for($tournament)->create();

    // Manually create the linked tracker game with NO scenario (mimics the
    // case where the TO paired before setting the round's scenario).
    $trackerGame = Game::factory()->create([
        'creator_id' => $creator->id,
        'season' => $tournament->season->value,
        'strategy_id' => null,
        'deployment' => null,
        'scheme_pool' => [],
        'is_solo' => true,
    ]);
    $tournamentGame = TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id,
        'player_two_id' => $p2->id,
        'game_id' => $trackerGame->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $trackerGame->id, 'user_id' => $creator->id, 'slot' => 1, 'scheme_pool' => []]);

    // Now the TO sets the scenario on the round.
    $strategy = Strategy::factory()->create();
    $round->update([
        'strategy_id' => $strategy->id,
        'deployment' => 'standard',
        'scheme_pool' => [1, 2, 3],
    ]);

    app(TournamentTrackerGameFactory::class)->syncScenarioForRound($round->fresh());

    $trackerGame->refresh();
    expect($trackerGame->strategy_id)->toBe($strategy->id);
    expect($trackerGame->deployment->value)->toBe('standard');
    expect($trackerGame->scheme_pool)->toBe([1, 2, 3]);

    $playerSnapshot = GamePlayer::where('game_id', $trackerGame->id)->first();
    expect($playerSnapshot->scheme_pool)->toBe([1, 2, 3]);
});

it('syncs scenario to surviving manual tracker games on re-pair', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->active()->create(['creator_id' => $creator->id]);

    $p1 = TournamentPlayer::factory()->for($tournament)->create(['user_id' => $creator->id]);
    $p2 = TournamentPlayer::factory()->for($tournament)->create();
    $p3 = TournamentPlayer::factory()->for($tournament)->create(['user_id' => User::factory()]);
    $p4 = TournamentPlayer::factory()->for($tournament)->create();

    // Round with stale scenario on the linked tracker game (mimics: TO paired,
    // then changed scenario, now is about to re-pair).
    $strategy = \App\Models\Strategy::factory()->create();
    $round = TournamentRound::factory()->for($tournament)->create([
        'strategy_id' => $strategy->id,
        'deployment' => 'standard',
        'scheme_pool' => [1, 2, 3],
    ]);

    // A manual pairing whose tracker game is on a STALE scenario.
    $staleTracker = Game::factory()->create([
        'creator_id' => $creator->id,
        'season' => $tournament->season->value,
        'strategy_id' => null,
        'deployment' => null,
        'scheme_pool' => [],
    ]);
    TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id,
        'player_two_id' => $p2->id,
        'is_manual' => true,
        'game_id' => $staleTracker->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $staleTracker->id, 'user_id' => $creator->id, 'slot' => 1, 'scheme_pool' => []]);

    // Trigger re-pair via the factory (mimics what the controller does).
    app(\App\Services\TournamentTrackerGameFactory::class)->createForRound($tournament, $round);

    $staleTracker->refresh();
    expect($staleTracker->strategy_id)->toBe($strategy->id);
    expect($staleTracker->deployment->value)->toBe('standard');
    expect($staleTracker->scheme_pool)->toBe([1, 2, 3]);
});

it('propagates scenario via the controller when updating round scenario', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->active()->create(['creator_id' => $creator->id]);

    $p1 = TournamentPlayer::factory()->for($tournament)->create(['user_id' => $creator->id]);
    $p2 = TournamentPlayer::factory()->for($tournament)->create();
    $round = TournamentRound::factory()->for($tournament)->create();
    $strategy = Strategy::factory()->create();

    $trackerGame = Game::factory()->create([
        'creator_id' => $creator->id,
        'season' => $tournament->season->value,
        'strategy_id' => null,
        'deployment' => null,
        'scheme_pool' => [],
    ]);
    TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
        'game_id' => $trackerGame->id,
    ]);
    $schemes = \App\Models\Scheme::factory()->count(3)->create();
    $schemeIds = $schemes->pluck('id')->all();

    test()->actingAs($creator)
        ->putJson(route('tournaments.rounds.update', [$tournament->uuid, $round]), [
            'strategy_id' => $strategy->id,
            'deployment' => 'standard',
            'scheme_pool' => $schemeIds,
        ])
        ->assertOk();

    $trackerGame->refresh();
    expect($trackerGame->strategy_id)->toBe($strategy->id);
    expect($trackerGame->deployment->value)->toBe('standard');
    expect($trackerGame->scheme_pool)->toBe($schemeIds);
});
