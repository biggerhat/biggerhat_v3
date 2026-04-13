<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentStandingsService;

it('preserves opponents scores when a player is dropped after playing a round', function () {
    $t = Tournament::factory()->active()->create();
    $a = TournamentPlayer::factory()->for($t)->create();
    $b = TournamentPlayer::factory()->for($t)->create();
    $r = TournamentRound::factory()->for($t)->completed()->create();

    // A beat B 8-2 in Round 1 — fully completed game with real scores.
    TournamentGame::factory()->for($r, 'round')->withScore(8, 2)->create([
        'player_one_id' => $a->id, 'player_two_id' => $b->id,
    ]);

    $service = app(TournamentStandingsService::class);

    $before = collect($service->compute($t))->keyBy('player_id');
    expect($before[$a->id]['total_tp'])->toBe(3);
    expect($before[$a->id]['total_diff'])->toBe(6);
    expect($before[$a->id]['total_vp'])->toBe(8);

    // B drops after Round 1.
    $b->update(['dropped_after_round' => 1]);

    $after = collect($service->compute($t->fresh(['players', 'rounds.games'])))->keyBy('player_id');
    // A's scores from beating B must NOT change.
    expect($after[$a->id]['total_tp'])->toBe(3)
        ->and($after[$a->id]['total_diff'])->toBe(6)
        ->and($after[$a->id]['total_vp'])->toBe(8);
});

it('respects per-tournament configurable bye scoring', function () {
    $t = Tournament::factory()->active()->create([
        'bye_tp' => 2, 'bye_diff' => 5, 'bye_vp' => 7,
    ]);
    $a = TournamentPlayer::factory()->for($t)->create();
    $r = TournamentRound::factory()->for($t)->completed()->create();
    TournamentGame::factory()->for($r, 'round')->bye()->create([
        'player_one_id' => $a->id, 'player_two_id' => null,
    ]);

    $standings = collect(app(TournamentStandingsService::class)->compute($t))->keyBy('player_id');

    expect($standings[$a->id])
        ->total_tp->toBe(2)
        ->total_diff->toBe(5)
        ->total_vp->toBe(7);
});
