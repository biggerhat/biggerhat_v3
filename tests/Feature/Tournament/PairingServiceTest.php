<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentPairingService;
use App\Services\TournamentStandingsService;

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

it('avoids same-meta pairings in round 1 whenever a clash-free pairing exists', function () {
    $metaA = \App\Models\Meta::factory()->create();
    $metaB = \App\Models\Meta::factory()->create();
    $metaC = \App\Models\Meta::factory()->create();

    $t = Tournament::factory()->active()->create();
    $players = collect([
        TournamentPlayer::factory()->for($t)->create(['meta_id' => $metaA->id]),
        TournamentPlayer::factory()->for($t)->create(['meta_id' => $metaA->id]),
        TournamentPlayer::factory()->for($t)->create(['meta_id' => $metaB->id]),
        TournamentPlayer::factory()->for($t)->create(['meta_id' => $metaC->id]),
    ]);
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1]);
    $metaOf = $players->pluck('meta_id', 'id');

    // Round 1 is shuffled, so run enough times to hit the orderings the greedy pass used to get wrong.
    foreach (range(1, 60) as $_) {
        $pairings = $this->service->generatePairings($t->fresh('players'), $round);

        expect($pairings)->toHaveCount(2);
        foreach ($pairings as $p) {
            expect($metaOf[$p['player_one_id']])->not->toBe($metaOf[$p['player_two_id']]);
        }
    }
});

it('still pairs everyone when same-meta pairings are unavoidable', function () {
    $meta = \App\Models\Meta::factory()->create();
    $t = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($t)->count(4)->create(['meta_id' => $meta->id]);
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1]);

    $pairings = $this->service->generatePairings($t->fresh('players'), $round);

    expect($pairings)->toHaveCount(2);
    expect(collect($pairings)->flatMap(fn ($p) => [$p['player_one_id'], $p['player_two_id']])->unique())->toHaveCount(4);
});

describe('matching quality', function () {
    function matchPlayersVia(array $players, array $previous, bool $firstRound = false): array
    {
        $service = app(TournamentPairingService::class);
        $method = new ReflectionMethod($service, 'matchPlayers');

        return array_map(
            fn ($pair) => [$pair[0]->id, $pair[1]->id],
            $method->invoke($service, collect($players), $previous, $firstRound),
        );
    }

    function fakePlayers(int $n): array
    {
        return collect(range(1, $n))->map(fn ($id) => (new TournamentPlayer)->forceFill(['id' => $id, 'meta_id' => null]))->all();
    }

    it('backtracks to avoid a rematch a greedy pass would be forced into', function () {
        // 5 and 6 already met. Greedy: 1-2, 3-4, then 5-6 (forced rematch). A rematch-free matching exists.
        $pairs = matchPlayersVia(fakePlayers(6), [5 => [6], 6 => [5]]);

        expect($pairs)->toHaveCount(3);
        expect($pairs)->not->toContain([5, 6]);
        expect($pairs[0])->toBe([1, 2]); // still prefers the closest standings where it can
        expect(collect($pairs)->flatten()->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6]);
    });

    it('accepts a rematch only when no rematch-free pairing exists', function () {
        // Everyone has played everyone else in a 4-player field: any pairing is a rematch.
        $previous = [1 => [2, 3, 4], 2 => [1, 3, 4], 3 => [1, 2, 4], 4 => [1, 2, 3]];

        expect(matchPlayersVia(fakePlayers(4), $previous))->toHaveCount(2);
    });

    it('leaves the lowest-ranked player unpaired when the count is odd', function () {
        $pairs = matchPlayersVia(fakePlayers(5), []);

        expect($pairs)->toBe([[1, 2], [3, 4]]);
    });

    it('stays fast on a large field with heavy rematch constraints', function () {
        $players = fakePlayers(64);
        $previous = [];
        foreach ($players as $p) {
            // each player has already faced the four players ranked just below them
            $previous[$p->id] = array_values(array_filter(range($p->id - 4, $p->id + 4), fn ($id) => $id >= 1 && $id <= 64 && $id !== $p->id));
        }

        $start = microtime(true);
        $pairs = matchPlayersVia($players, $previous);

        expect(microtime(true) - $start)->toBeLessThan(2.0);
        expect($pairs)->toHaveCount(32);
        foreach ($pairs as [$a, $b]) {
            expect($previous[$a])->not->toContain($b);
        }
    });

    it('pairs ties in random order rather than database order', function () {
        $t = Tournament::factory()->active()->create();
        TournamentPlayer::factory()->for($t)->count(4)->create();
        $round = TournamentRound::factory()->for($t)->create(['round_number' => 2]);
        $service = app(TournamentPairingService::class);

        $seen = [];
        for ($i = 0; $i < 30; $i++) {
            $key = collect($service->generatePairings($t->fresh('players'), $round))
                ->map(fn ($p) => collect([$p['player_one_id'], $p['player_two_id']])->sort()->implode('-'))
                ->sort()->implode('|');
            $seen[$key] = true;
        }

        expect(count($seen))->toBeGreaterThan(1);
    });
});

it('pairs from the ruleset-ordered standings rank, not a fixed TP/DIFF/VP order', function () {
    $t = Tournament::factory()->active()->create(['ruleset' => 'tos_fields_of_glory']);
    [$a, $b, $c, $d] = TournamentPlayer::factory()->for($t)->count(4)->create()->all();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 2]);

    // Every raw total is level, so the old TP → DIFF → VP sort would pair at random; only `rank` (SoS-first for this
    // ruleset) says who sits next to whom.
    $row = fn (TournamentPlayer $p, int $rank) => ['player_id' => $p->id, 'rank' => $rank, 'total_tp' => 0, 'total_diff' => 0, 'total_vp' => 0];
    $standings = Mockery::mock(TournamentStandingsService::class);
    $standings->shouldReceive('compute')->andReturn([$row($d, 1), $row($b, 2), $row($a, 3), $row($c, 4)]);
    $service = new TournamentPairingService($standings);

    foreach (range(1, 12) as $_) {
        $pairs = collect($service->generatePairings($t->fresh('players'), $round))
            ->map(fn ($p) => collect([$p['player_one_id'], $p['player_two_id']])->sort()->values()->all())
            ->sort()->values()->all();

        expect($pairs)->toBe(collect([[$d->id, $b->id], [$a->id, $c->id]])->map(fn ($x) => collect($x)->sort()->values()->all())->sort()->values()->all());
    }
});

it('keeps players on a joint rank in random order', function () {
    $t = Tournament::factory()->active()->create();
    $players = TournamentPlayer::factory()->for($t)->count(4)->create();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 2]);

    $standings = Mockery::mock(TournamentStandingsService::class);
    $standings->shouldReceive('compute')->andReturn($players->map(fn ($p) => ['player_id' => $p->id, 'rank' => 1])->all());
    $service = new TournamentPairingService($standings);

    $seen = [];
    foreach (range(1, 40) as $_) {
        $first = collect($service->generatePairings($t->fresh('players'), $round))->first();
        $seen[$first['player_one_id'].'-'.$first['player_two_id']] = true;
    }

    expect(count($seen))->toBeGreaterThan(1);
});

it('keeps a player off a table they already played on when the free tables allow it', function () {
    $t = Tournament::factory()->active()->create();
    [$a, $b, $c, $d] = TournamentPlayer::factory()->for($t)->count(4)->create()->all();
    $gone = TournamentPlayer::factory()->for($t)->create(['dropped_after_round' => 1]);
    $one = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => 'completed']);
    TournamentGame::factory()->for($one, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $gone->id, 'table_number' => 1]);
    $two = TournamentRound::factory()->for($t)->create(['round_number' => 2]);

    $this->service->regeneratePairings($t->fresh('players'), $two);

    $games = TournamentGame::where('tournament_round_id', $two->id)->get();
    expect($games)->toHaveCount(2)
        ->and($games->pluck('table_number')->sort()->values()->all())->toBe([1, 2])
        ->and($games->first(fn ($g) => in_array($a->id, [$g->player_one_id, $g->player_two_id], true))->table_number)->toBe(2);
});

it('numbers tables from 1 and skips tables held by manual pairings', function () {
    $t = Tournament::factory()->active()->create();
    [$a, $b, $c, $d] = TournamentPlayer::factory()->for($t)->count(4)->create()->all();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1]);
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 1, 'is_manual' => true]);

    $this->service->regeneratePairings($t->fresh('players'), $round);

    expect(TournamentGame::where('tournament_round_id', $round->id)->where('is_manual', false)->value('table_number'))->toBe(2);
});
