<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;

beforeEach(function () {
    // Mirror production: prevent accessing missing model attributes so we
    // catch column-restricted eager loads that break appended attributes.
    \Illuminate\Database\Eloquent\Model::preventAccessingMissingAttributes(true);
});

it('renders the public view page for a tournament with a linked Game tracker game', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->active()->create(['creator_id' => $creator->id]);

    $p1 = TournamentPlayer::factory()->for($tournament)->create();
    $p2 = TournamentPlayer::factory()->for($tournament)->create();
    $round = TournamentRound::factory()->for($tournament)->inProgress()->create();

    // A Game tracker game linked to a tournament game — this is the path
    // that historically blew up because the eager load only fetched id+uuid
    // and the Game model has $appends = ['season_label'] which needs `season`.
    $trackerGame = Game::factory()->create([
        'creator_id' => $creator->id,
        'season' => $tournament->season->value,
    ]);

    TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id,
        'player_two_id' => $p2->id,
        'game_id' => $trackerGame->id,
    ]);

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('tournaments.view', $tournament->uuid))
        ->assertOk();
});

it('renders the public view page for a guest', function () {
    $tournament = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($tournament)->count(2)->create();

    $this->get(route('tournaments.view', $tournament->uuid))
        ->assertOk();
});
