<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentStandingsService;

beforeEach(function () {
    $this->service = app(TournamentStandingsService::class);
});

it('awards 3 TP for a win and 1 for a draw', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();
    $winner = TournamentPlayer::factory()->for($t)->create();
    $loser = TournamentPlayer::factory()->for($t)->create();
    $a = TournamentPlayer::factory()->for($t)->create();
    $b = TournamentPlayer::factory()->for($t)->create();

    TournamentGame::factory()->for($r, 'round')->withScore(8, 3)->create([
        'player_one_id' => $winner->id, 'player_two_id' => $loser->id,
    ]);
    TournamentGame::factory()->for($r, 'round')->withScore(5, 5)->create([
        'player_one_id' => $a->id, 'player_two_id' => $b->id,
    ]);

    $standings = collect($this->service->compute($t))->keyBy('player_id');

    expect($standings[$winner->id]['total_tp'])->toBe(3);
    expect($standings[$winner->id]['total_diff'])->toBe(5);
    expect($standings[$loser->id]['total_tp'])->toBe(0);
    expect($standings[$loser->id]['total_diff'])->toBe(-5);
    expect($standings[$a->id]['total_tp'])->toBe(1);
    expect($standings[$b->id]['total_tp'])->toBe(1);
});

it('awards bye points (3 TP, +4 DIFF, 6 VP)', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();
    $byer = TournamentPlayer::factory()->for($t)->create();

    TournamentGame::factory()->for($r, 'round')->bye()->create([
        'player_one_id' => $byer->id, 'player_two_id' => null,
    ]);

    $standings = collect($this->service->compute($t))->keyBy('player_id');

    expect($standings[$byer->id])
        ->total_tp->toBe(3)
        ->total_diff->toBe(4)
        ->total_vp->toBe(6)
        ->has_bye->toBeTrue();
});

it('handles forfeit: winner gets 3 TP +11 DIFF, loser -11 DIFF', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();
    $winner = TournamentPlayer::factory()->for($t)->create();
    $loser = TournamentPlayer::factory()->for($t)->create();

    TournamentGame::factory()->for($r, 'round')->forfeit($loser->id)->create([
        'player_one_id' => $winner->id, 'player_two_id' => $loser->id,
    ]);

    $standings = collect($this->service->compute($t))->keyBy('player_id');

    expect($standings[$winner->id])->total_tp->toBe(3)->total_diff->toBe(11)->total_vp->toBe(11);
    expect($standings[$loser->id])->total_tp->toBe(0)->total_diff->toBe(-11);
});

it('ranks by TP, then DIFF, then VP, with joint placing on full ties', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();

    $players = TournamentPlayer::factory()->for($t)->count(4)->create();
    [$a, $b, $c, $d] = $players->all();

    // a beats b 8-2; c beats d 8-2 — a and c are tied (3 TP, +6 DIFF, 8 VP)
    TournamentGame::factory()->for($r, 'round')->withScore(8, 2)->create([
        'player_one_id' => $a->id, 'player_two_id' => $b->id,
    ]);
    TournamentGame::factory()->for($r, 'round')->withScore(8, 2)->create([
        'player_one_id' => $c->id, 'player_two_id' => $d->id,
    ]);

    $standings = $this->service->compute($t);

    // Top two should both be rank 1 (joint)
    expect($standings[0]['rank'])->toBe(1);
    expect($standings[1]['rank'])->toBe(1);
    expect($standings[2]['rank'])->toBe(3);
    expect($standings[3]['rank'])->toBe(3);
});

it('keeps ringer unranked but listed at the bottom', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();
    $regular = TournamentPlayer::factory()->for($t)->create();
    $ringer = TournamentPlayer::factory()->for($t)->ringer()->create();

    TournamentGame::factory()->for($r, 'round')->withScore(7, 5)->create([
        'player_one_id' => $regular->id, 'player_two_id' => $ringer->id,
    ]);

    $standings = $this->service->compute($t);
    $ringerEntry = collect($standings)->firstWhere('player_id', $ringer->id);

    expect($ringerEntry['is_ringer'])->toBeTrue();
    expect($ringerEntry['rank'])->toBeNull();
    expect(end($standings)['player_id'])->toBe($ringer->id);
});

it('skips pending games and disqualified players', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();
    $p1 = TournamentPlayer::factory()->for($t)->create();
    $p2 = TournamentPlayer::factory()->for($t)->create();
    $dq = TournamentPlayer::factory()->for($t)->create(['is_disqualified' => true]);

    TournamentGame::factory()->for($r, 'round')->create([
        'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
    ]);

    $standings = $this->service->compute($t);

    expect(collect($standings)->pluck('player_id'))->not->toContain($dq->id);
    foreach ($standings as $entry) {
        // Pending game means everyone has 0 TP / 0 rounds_played
        expect($entry['rounds_played'])->toBe(0);
    }
});
