<?php

use App\Models\Meta;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentPairingService;

it('preserves manual pairings when auto-pair fills in the rest', function () {
    $t = Tournament::factory()->active()->create();
    $players = TournamentPlayer::factory()->for($t)->count(6)->create();
    [$a, $b, $c, $d, $e, $f] = $players->all();
    $r = TournamentRound::factory()->for($t)->create(['round_number' => 1]);

    // TO manually pairs A vs B at table 1.
    TournamentGame::factory()->for($r, 'round')->create([
        'player_one_id' => $a->id,
        'player_two_id' => $b->id,
        'is_manual' => true,
        'table_number' => 1,
    ]);

    // Auto-pair the rest — should pair the remaining 4 (C/D/E/F) into 2 games.
    $alreadyPaired = [$a->id => true, $b->id => true];
    $pairings = app(TournamentPairingService::class)->generatePairings($t->fresh(), $r, $alreadyPaired);

    expect($pairings)->toHaveCount(2);

    $pairedIds = collect($pairings)
        ->flatMap(fn ($p) => array_filter([$p['player_one_id'], $p['player_two_id']]))
        ->all();
    expect($pairedIds)->not->toContain($a->id);
    expect($pairedIds)->not->toContain($b->id);
    expect(array_unique($pairedIds))->toHaveCount(4);
});

it('avoids pairing same-meta players in Round 1 when alternatives exist', function () {
    $bostonMeta = Meta::factory()->create(['name' => 'Boston']);
    $nycMeta = Meta::factory()->create(['name' => 'NYC']);

    $t = Tournament::factory()->active()->create();
    // 4 players: 2 from Boston, 2 from NYC. Cross-meta pairings ARE possible.
    $b1 = TournamentPlayer::factory()->for($t)->create(['meta_id' => $bostonMeta->id]);
    $b2 = TournamentPlayer::factory()->for($t)->create(['meta_id' => $bostonMeta->id]);
    $n1 = TournamentPlayer::factory()->for($t)->create(['meta_id' => $nycMeta->id]);
    $n2 = TournamentPlayer::factory()->for($t)->create(['meta_id' => $nycMeta->id]);

    $r = TournamentRound::factory()->for($t)->create(['round_number' => 1]);
    $pairings = app(TournamentPairingService::class)->generatePairings($t->fresh(), $r);

    // Verify no pairing has both players from the same meta.
    $playerMeta = collect([$b1, $b2, $n1, $n2])->keyBy('id')->map(fn ($p) => $p->meta_id);

    foreach ($pairings as $p) {
        $m1 = $playerMeta[$p['player_one_id']];
        $m2 = $p['player_two_id'] ? $playerMeta[$p['player_two_id']] : null;
        if ($m1 !== null && $m2 !== null) {
            expect($m1)->not->toBe($m2, 'Should not pair two same-meta players in Round 1 when avoidable');
        }
    }
});

it('falls back to same-meta pairings in Round 1 when there is no choice', function () {
    $boston = Meta::factory()->create(['name' => 'Boston']);

    $t = Tournament::factory()->active()->create();
    // Everyone is in the same meta — pairing has no other option.
    TournamentPlayer::factory()->for($t)->count(4)->create(['meta_id' => $boston->id]);

    $r = TournamentRound::factory()->for($t)->create(['round_number' => 1]);
    $pairings = app(TournamentPairingService::class)->generatePairings($t->fresh(), $r);

    expect($pairings)->toHaveCount(2);
    // Doesn't error out — same-meta pairings are accepted as a last resort.
});

it('still pairs successfully when the tournament uses SoS tiebreaker mode', function () {
    // Pairing for round 2+ calls standings->compute() to sort by standings.
    // With SoS mode, that pulls in the SoS calculator + missed-round penalty
    // pipeline. Make sure that path doesn't error out and still produces pairings.
    $t = Tournament::factory()->active()->create(['tiebreaker_mode' => 'sos']);
    $players = TournamentPlayer::factory()->for($t)->count(4)->create();
    [$a, $b, $c, $d] = $players->all();

    $r1 = TournamentRound::factory()->for($t)->completed()->create(['round_number' => 1]);
    TournamentGame::factory()->for($r1, 'round')->withScore(8, 2)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);
    TournamentGame::factory()->for($r1, 'round')->withScore(7, 4)->create(['player_one_id' => $c->id, 'player_two_id' => $d->id]);

    $r2 = TournamentRound::factory()->for($t)->create(['round_number' => 2]);
    $pairings = app(TournamentPairingService::class)->generatePairings($t->fresh(), $r2);

    expect($pairings)->toHaveCount(2);
    foreach ($pairings as $p) {
        expect($p['player_one_id'])->toBeInt();
        expect($p['player_two_id'])->toBeInt();
    }
});

it('ignores meta when pairing Round 2+ (Swiss takes over)', function () {
    $boston = Meta::factory()->create(['name' => 'Boston']);
    $t = Tournament::factory()->active()->create();
    $b1 = TournamentPlayer::factory()->for($t)->create(['meta_id' => $boston->id]);
    $b2 = TournamentPlayer::factory()->for($t)->create(['meta_id' => $boston->id]);
    $other = TournamentPlayer::factory()->for($t)->create();
    $other2 = TournamentPlayer::factory()->for($t)->create();

    // Round 1 already played: b1 vs other, b2 vs other2 (no rematches in R2).
    $r1 = TournamentRound::factory()->for($t)->completed()->create(['round_number' => 1]);
    TournamentGame::factory()->for($r1, 'round')->withScore(8, 2)->create(['player_one_id' => $b1->id, 'player_two_id' => $other->id]);
    TournamentGame::factory()->for($r1, 'round')->withScore(7, 4)->create(['player_one_id' => $b2->id, 'player_two_id' => $other2->id]);

    $r2 = TournamentRound::factory()->for($t)->create(['round_number' => 2]);
    $pairings = app(TournamentPairingService::class)->generatePairings($t->fresh(), $r2);

    // R2 should pair by standings; meta-avoidance does NOT apply post-R1.
    // We don't assert that b1 plays b2 here (Swiss might pair differently),
    // but if it did, that's fine — meta avoidance is intentionally R1-only.
    expect($pairings)->toHaveCount(2);
});
