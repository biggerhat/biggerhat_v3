<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentPairingService;

beforeEach(function () {
    $this->service = app(TournamentPairingService::class);
});

it('pairs everyone exactly once when player count is even', function () {
    $t = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($t)->count(6)->create();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1]);

    $pairings = $this->service->generatePairings($t->fresh('players'), $round);

    expect($pairings)->toHaveCount(3);

    $paired = collect($pairings)
        ->flatMap(fn ($p) => array_filter([$p['player_one_id'], $p['player_two_id']]))
        ->all();
    expect($paired)->toHaveCount(6);
    expect(array_unique($paired))->toHaveCount(6);
});

it('assigns a bye when player count is odd and there is no ringer', function () {
    $t = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($t)->count(5)->create();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1]);

    $pairings = $this->service->generatePairings($t->fresh('players'), $round);

    $byes = array_filter($pairings, fn ($p) => $p['is_bye']);
    expect($byes)->toHaveCount(1);
});

it('uses the ringer when there is one and player count is odd', function () {
    $t = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($t)->count(5)->create();
    $ringer = TournamentPlayer::factory()->for($t)->ringer()->create();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1]);

    $pairings = $this->service->generatePairings($t->fresh('players'), $round);

    $ringerPlays = collect($pairings)->contains(fn ($p) => $p['player_two_id'] === $ringer->id || $p['player_one_id'] === $ringer->id);
    $byes = array_filter($pairings, fn ($p) => $p['is_bye']);

    expect($ringerPlays)->toBeTrue();
    expect($byes)->toBeEmpty();
});

it('avoids pairing players who have already played each other', function () {
    $t = Tournament::factory()->active()->create();
    $players = TournamentPlayer::factory()->for($t)->count(4)->create();
    [$a, $b, $c, $d] = $players->all();

    // Round 1: a-b, c-d
    $r1 = TournamentRound::factory()->for($t)->completed()->create(['round_number' => 1]);
    TournamentGame::factory()->for($r1, 'round')->withScore(8, 2)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);
    TournamentGame::factory()->for($r1, 'round')->withScore(7, 4)->create(['player_one_id' => $c->id, 'player_two_id' => $d->id]);

    $r2 = TournamentRound::factory()->for($t)->create(['round_number' => 2]);
    $pairings = $this->service->generatePairings($t->fresh('players'), $r2);

    foreach ($pairings as $p) {
        $pair = [$p['player_one_id'], $p['player_two_id']];
        sort($pair);
        // No rematch: shouldn't see (a,b) or (c,d) again
        expect($pair)->not->toBe([min($a->id, $b->id), max($a->id, $b->id)]);
        expect($pair)->not->toBe([min($c->id, $d->id), max($c->id, $d->id)]);
    }
});

it('excludes dropped players from active pairing', function () {
    $t = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($t)->count(3)->create();
    $dropped = TournamentPlayer::factory()->for($t)->create(['dropped_after_round' => 1]);
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 2]);

    $pairings = $this->service->generatePairings($t->fresh('players'), $round);

    $allIds = collect($pairings)->flatMap(fn ($p) => [$p['player_one_id'], $p['player_two_id']])->filter()->all();
    expect($allIds)->not->toContain($dropped->id);
});
