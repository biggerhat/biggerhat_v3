<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentStandingsService;

function fogTournament(): Tournament
{
    return Tournament::factory()->active()->create(['ruleset' => 'tos_fields_of_glory']);
}

it('defaults existing tournaments to Gaining Grounds', function () {
    $t = Tournament::factory()->create();

    expect($t->fresh()->ruleset)->toBe('malifaux_gaining_grounds');
    expect($t->scoringConfig()->byeTp)->toBe(3);
    expect($t->tiebreakerKeys())->toBe(['diff', 'vp']);
});

it('scores a Fields of Glory game from the packet example', function () {
    $t = fogTournament();
    $r = TournamentRound::factory()->for($t)->create();
    $a = TournamentPlayer::factory()->for($t)->create();
    $b = TournamentPlayer::factory()->for($t)->create();
    TournamentGame::factory()->for($r, 'round')->withScore(8, 4)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);

    $s = collect(app(TournamentStandingsService::class)->compute($t))->keyBy('player_id');

    expect($s[$a->id])->total_tp->toBe(16)->total_diff->toBe(4)->total_vp->toBe(8);
    expect($s[$b->id])->total_tp->toBe(6)->total_diff->toBe(-4)->total_vp->toBe(4);
});

it('uses the Fields of Glory bye and forfeit values', function () {
    $t = fogTournament();
    $r = TournamentRound::factory()->for($t)->create();
    $byer = TournamentPlayer::factory()->for($t)->create();
    $winner = TournamentPlayer::factory()->for($t)->create();
    $loser = TournamentPlayer::factory()->for($t)->create();
    TournamentGame::factory()->for($r, 'round')->bye()->create(['player_one_id' => $byer->id, 'player_two_id' => null]);
    TournamentGame::factory()->for($r, 'round')->forfeit($loser->id)->create(['player_one_id' => $winner->id, 'player_two_id' => $loser->id]);

    $s = collect(app(TournamentStandingsService::class)->compute($t))->keyBy('player_id');

    expect($s[$byer->id])->total_tp->toBe(19)->total_diff->toBe(7)->total_vp->toBe(7);
    expect($s[$winner->id])->total_tp->toBe(20)->total_diff->toBe(8)->total_vp->toBe(8);
    expect($s[$loser->id])->total_tp->toBe(0)->total_diff->toBe(-8)->total_vp->toBe(0);
});

it('ranks Fields of Glory by TP then SoS only, sharing a placing when DIFF differs', function () {
    $t = fogTournament();
    expect($t->tiebreakerKeys())->toBe(['sos']);

    $r = TournamentRound::factory()->for($t)->create();
    [$a, $b, $c, $d] = TournamentPlayer::factory()->for($t)->count(4)->create()->all();
    // a beats b 8-2, c beats d 5-4: a = 10+6+2 = 18 TP, c = 10+1+2 = 13 TP. Distinct; check ordering + TP.
    TournamentGame::factory()->for($r, 'round')->withScore(8, 2)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);
    TournamentGame::factory()->for($r, 'round')->withScore(5, 4)->create(['player_one_id' => $c->id, 'player_two_id' => $d->id]);

    $s = collect(app(TournamentStandingsService::class)->compute($t));

    expect($s->pluck('player_id')->take(2)->all())->toBe([$a->id, $c->id]);
});

it('respects per-tournament scoring overrides', function () {
    $t = Tournament::factory()->active()->create(['bye_tp' => 2, 'scoring_config' => ['forfeit_vp' => 9]]);

    expect($t->scoringConfig())->byeTp->toBe(2)->forfeitVp->toBe(9)->byeDiff->toBe(4);
});
