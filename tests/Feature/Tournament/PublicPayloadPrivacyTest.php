<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organizer = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->organizer->id, 'allow_self_report' => true]);

    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $p1 = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->alice->id]);
    $p2 = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->bob->id]);
    $round = TournamentRound::factory()->for($this->tournament)->inProgress()->create();

    $tracker = Game::factory()->create([
        'creator_id' => $this->alice->id,
        'season' => $this->tournament->season->value,
        'settings' => ['private_note' => 'secret'],
    ]);

    TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id,
        'player_two_id' => $p2->id,
        'game_id' => $tracker->id,
        'report_status' => 'disputed',
        'reported_by_player_id' => $p1->id,
        'reported_score' => ['player_one' => ['strategy' => 5], 'player_two' => ['strategy' => 0], 'agreed' => false],
        'report_note' => 'he cheated on the scheme marker',
    ]);
});

function gameProps(?User $viewer, Tournament $tournament)
{
    $test = $viewer ? test()->actingAs($viewer) : test();

    return $test->get(route('tournaments.view', $tournament->uuid))->assertOk();
}

it('hides report details and private columns from guests', function () {
    gameProps(null, $this->tournament)->assertInertia(fn (Assert $page) => $page
        ->where('rounds.0.games.0.report_status', 'disputed')
        ->missing('rounds.0.games.0.report_note')
        ->missing('rounds.0.games.0.reported_score')
        ->missing('rounds.0.games.0.reported_by_player_id')
        ->missing('rounds.0.games.0.player_one_crew_build_id')
        ->missing('rounds.0.games.0.tracker_game.settings')
        ->missing('rounds.0.games.0.tracker_game.loot_state')
        ->missing('rounds.0.games.0.tracker_game.creator_id')
        ->missing('tournament.players.0.user.meta_id')
        ->missing('tournament.players.0.meta'));
});

it('hides report details from a signed-in user who is not in the game', function () {
    gameProps(User::factory()->create(), $this->tournament)->assertInertia(fn (Assert $page) => $page
        ->missing('rounds.0.games.0.report_note')
        ->missing('rounds.0.games.0.reported_score'));
});

it('shows report details to both sides of the game', function () {
    foreach ([$this->alice, $this->bob] as $player) {
        gameProps($player, $this->tournament)->assertInertia(fn (Assert $page) => $page
            ->where('rounds.0.games.0.report_note', 'he cheated on the scheme marker')
            ->has('rounds.0.games.0.reported_score'));
    }
});

it('shows report details to the organizer', function () {
    gameProps($this->organizer, $this->tournament)->assertInertia(fn (Assert $page) => $page
        ->where('rounds.0.games.0.report_note', 'he cheated on the scheme marker'));
});

it('applies the same redaction to display mode', function () {
    test()->get(route('tournaments.display', $this->tournament->uuid))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->missing('rounds.0.games.0.report_note')
        ->missing('rounds.0.games.0.tracker_game.settings'));
});
