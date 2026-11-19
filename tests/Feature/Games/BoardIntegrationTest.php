<?php

use App\Enums\GameRoleEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Events\GameBoardAurasChanged;
use App\Events\GameBoardMarkersChanged;
use App\Events\GameBoardMoved;
use App\Events\GameBoardSetupChanged;
use App\Events\GameBoardTerrainChanged;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Services\Game\RoundClock;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Feature::for(null)->activate('experiment-tabletop');
});

/** A game of two crews, optionally part of a running tournament round that started $minutesAgo minutes ago. */
function integrationGame(?int $minutesAgo = null, bool $observable = false): array
{
    $a = User::factory()->create();
    $b = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $a->id, 'is_solo' => false, 'is_observable' => $observable, 'current_turn' => 1, 'max_turns' => 4]);
    $p1 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $a->id, 'slot' => 1, 'role' => GameRoleEnum::Attacker]);
    $p2 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $b->id, 'slot' => 2, 'role' => GameRoleEnum::Defender]);
    $m1 = GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $p1->id]);
    GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $p2->id]);

    if ($minutesAgo !== null) {
        $tournament = Tournament::factory()->active()->create(['creator_id' => $a->id, 'round_time_limit' => 135]);
        $round = TournamentRound::factory()->for($tournament)->create([
            'round_number' => 1,
            'status' => TournamentRoundStatusEnum::InProgress,
            'started_at' => Carbon::now()->subMinutes($minutesAgo),
        ]);
        $one = TournamentPlayer::factory()->for($tournament)->create();
        $two = TournamentPlayer::factory()->for($tournament)->create();
        TournamentGame::factory()->for($round, 'round')->create(['game_id' => $game->id, 'player_one_id' => $one->id, 'player_two_id' => $two->id, 'table_number' => 1]);
    }

    return [$game, $p1, $p2, $a, $b, $m1];
}

it('gives a tournament game the clock of its round: when it ends and when last turn is called', function () {
    Carbon::setTestNow('2026-12-01 12:00:00');
    [$game, , , $a] = integrationGame(minutesAgo: 100);

    $clock = app(RoundClock::class)->for($game->fresh());

    expect($clock['limit'])->toBe(135)
        ->and(Carbon::parse($clock['ends_at'])->toDateTimeString())->toBe('2026-12-01 12:35:00')
        ->and(Carbon::parse($clock['last_turn_at'])->toDateTimeString())->toBe('2026-12-01 12:20:00')
        ->and(Carbon::parse($clock['now'])->toDateTimeString())->toBe('2026-12-01 12:00:00');
    $this->actingAs($a)->get(route('games.show', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('tournament_clock.limit', 135));
    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('clock.limit', 135));
    Carbon::setTestNow();
});

it('has no clock for a game outside a running round', function () {
    [$game, , , $a] = integrationGame();

    expect(app(RoundClock::class)->for($game))->toBeNull();
    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('clock', null));
});

it('keeps where the models stood at the end of each turn', function () {
    [$game, , , $a, $b, $m1] = integrationGame();
    $game->update(['board_state' => ['positions' => [(string) $m1->id => ['x' => 7, 'y' => 9]], 'markers' => [['id' => 'm1', 'marker_id' => 1, 'owner' => 1, 'x' => 3, 'y' => 4]]]]);
    $score = ['strategy_points' => 0, 'scheme_points' => 0, 'scheme_action' => 'held'];

    $this->actingAs($a)->postJson(route('games.play.turns.store', $game->uuid), $score)->assertOk();
    $this->actingAs($b)->postJson(route('games.play.turns.store', $game->uuid), $score)->assertOk();
    // the table moves on in turn 2
    $game->refresh();
    $game->update(['board_state' => array_merge($game->board_state, ['positions' => [(string) $m1->id => ['x' => 20, 'y' => 20]]])]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('history.1.positions.'.$m1->id.'.x', 7)
            ->where('history.1.markers.0.id', 'm1')
            ->where('positions.'.$m1->id.'.x', 20));
});

it('shows only a player their own scheme notes on the board', function () {
    [$game, $p1, $p2, $a, $b] = integrationGame(observable: true);
    $p1->update(['scheme_notes' => ['selected_model' => 'Pandora', 'note' => 'secret']]);
    $p2->update(['scheme_notes' => ['selected_model' => 'Lady Justice']]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('players.0.scheme_notes.selected_model', 'Pandora')->where('players.1.scheme_notes', null));
    // someone watching is given neither
    $this->actingAs(User::factory()->create())->get(route('games.board.watch', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('players.0.scheme_notes', null)->where('players.1.scheme_notes', null));
});

it('sends board changes to the public channel only for a game that can be watched', function () {
    [$private] = integrationGame();
    [$watched] = integrationGame(observable: true);
    $events = fn (Game $game) => [
        new GameBoardMoved($game, []),
        new GameBoardTerrainChanged($game, []),
        new GameBoardMarkersChanged($game, []),
        new GameBoardAurasChanged($game, []),
        new GameBoardSetupChanged($game, 0, null),
    ];

    foreach ($events($private) as $event) {
        expect(array_map(fn ($c) => $c->name, $event->broadcastOn()))->toBe(['presence-game.'.$private->uuid]);
    }
    foreach ($events($watched) as $event) {
        expect(array_map(fn ($c) => $c->name, $event->broadcastOn()))->toBe(['presence-game.'.$watched->uuid, 'game-observe.'.$watched->uuid]);
    }
});

it('puts the turn on the board payload, for players and watchers', function () {
    [$game, , , $a] = integrationGame(observable: true);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('turn.initiative', 1)->where('turn.active', 1)->where('turn.unactivated.1', 1));
    $this->actingAs(User::factory()->create())->get(route('games.board.watch', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('turn.turn', 1));
});
