<?php

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Services\TournamentCutService;
use App\Services\TournamentStandingsService;
use Inertia\Testing\AssertableInertia as Assert;

/** Eight players who each beat everyone below them in one finished Swiss round, so the seeding is P1 > P2 > ... > P8. */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'planned_rounds' => 1]);
    $this->players = collect(range(1, 8))->map(fn ($n) => TournamentPlayer::factory()->for($this->tournament)->create(['display_name' => "P$n", 'faction' => 'guild']))->all();

    $swiss = TournamentRound::factory()->for($this->tournament)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::Completed]);
    // Pair 1v2 3v4 5v6 7v8 where the lower number wins, then make seeds distinct through margins: (P1 11-0, P2 .., ) are enough via TP/VP.
    foreach ([[0, 1, 9, 2], [2, 3, 8, 3], [4, 5, 7, 4], [6, 7, 6, 5]] as $i => [$a, $b, $x, $y]) {
        TournamentGame::factory()->for($swiss, 'round')->create([
            'player_one_id' => $this->players[$a]->id, 'player_two_id' => $this->players[$b]->id, 'table_number' => $i + 1,
            'result' => TournamentGameResultEnum::Completed, 'player_one_vp' => $x, 'player_two_vp' => $y,
        ]);
    }
    $this->url = fn (string $name) => route("tournaments.cut.$name", $this->tournament);
    $this->score = function (TournamentGame $game, int $one, int $two) {
        $game->update(['player_one_vp' => $one, 'player_two_vp' => $two, 'result' => TournamentGameResultEnum::Completed]);
    };
    $this->finish = fn (TournamentRound $round) => $round->update(['status' => TournamentRoundStatusEnum::Completed]);
});

it('orders the bracket so seeds meet late', function () {
    $service = app(TournamentCutService::class);

    expect($service->bracketOrder(2))->toBe([1, 2])
        ->and($service->bracketOrder(4))->toBe([1, 4, 2, 3])
        ->and($service->bracketOrder(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6]);
});

it('offers the cut sizes the field allows once the Swiss rounds are done', function () {
    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament))
        ->assertInertia(fn (Assert $page) => $page->where('cut.available_sizes', [2, 4, 8])->where('cut.started', false)->where('cut.block_reason', null));
});

it('seeds the cut from the Swiss standings and pairs it in bracket order', function () {
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();

    $round = $this->tournament->rounds()->where('is_cut', true)->with('games.playerOne', 'games.playerTwo')->firstOrFail();
    $seeds = collect(app(TournamentStandingsService::class)->computeSwiss($this->tournament->fresh()))->pluck('display_name')->all();
    $names = fn (TournamentPlayer $p) => $p->display_name;
    $pairs = $round->games->map(fn ($g) => [$g->playerOne->display_name, $g->playerTwo->display_name])->all();

    expect($round->round_number)->toBe(2)->and($round->status)->toBe(TournamentRoundStatusEnum::Setup)->and($this->tournament->fresh()->cut_size)->toBe(4)
        ->and($pairs)->toBe([[$seeds[0], $seeds[3]], [$seeds[1], $seeds[2]]])
        ->and($round->games->pluck('table_number')->all())->toBe([1, 2])
        ->and($round->label)->toBe('Semifinals');
});

it('refuses a cut before the Swiss rounds are finished, twice, or too big for the field', function () {
    $this->tournament->rounds()->first()->update(['status' => TournamentRoundStatusEnum::InProgress]);
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertStatus(422)->assertJsonPath('error', 'Finish every Swiss round before starting the top cut.');

    $this->tournament->rounds()->first()->update(['status' => TournamentRoundStatusEnum::Completed]);
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 5])->assertJsonValidationErrors('size');
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 16])->assertStatus(422);

    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertStatus(422)->assertJsonPath('error', 'The top cut has already started.');
});

it('only lets organizers run the cut', function () {
    $this->actingAs(User::factory()->create())->postJson(($this->url)('start'), ['size' => 4])->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson(($this->url)('advance'))->assertForbidden();
});

it('leaves dropped players out of the seeding', function () {
    $this->players[0]->update(['dropped_after_round' => 1]);

    $this->actingAs($this->owner)->getJson(route('tournaments.manage', $this->tournament))->assertOk();
    expect(app(TournamentCutService::class)->seeds($this->tournament->fresh()))->not->toContain($this->players[0]->id)->toHaveCount(7);
});

it('advances winners to the final and places everyone when it is decided', function () {
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $semis = $this->tournament->rounds()->where('is_cut', true)->firstOrFail();
    $standings = collect(app(TournamentStandingsService::class)->computeSwiss($this->tournament->fresh()))->pluck('display_name')->all();

    // advance is refused until the semifinals are played out
    $this->actingAs($this->owner)->postJson(($this->url)('advance'))->assertStatus(422);
    $semis->update(['status' => TournamentRoundStatusEnum::InProgress]);
    $games = $semis->games()->get();
    ($this->score)($games[0], 3, 9); // seed 4 upsets seed 1
    ($this->score)($games[1], 8, 2); // seed 2 beats seed 3
    ($this->finish)($semis);

    $this->actingAs($this->owner)->postJson(($this->url)('advance'))->assertOk();
    $final = $this->tournament->rounds()->where('is_cut', true)->reorder('round_number', 'desc')->with('games.playerOne', 'games.playerTwo')->firstOrFail();
    $pair = [$final->games[0]->playerOne->display_name, $final->games[0]->playerTwo->display_name];

    expect($final->games)->toHaveCount(1)->and($final->label)->toBe('Finals')->and($pair)->toBe([$standings[3], $standings[1]]);

    // the finalize guard holds until the final is played
    $this->tournament->rounds()->where('is_cut', false)->update(['status' => TournamentRoundStatusEnum::Completed]);
    $final->update(['status' => TournamentRoundStatusEnum::InProgress]);
    ($this->score)($final->games[0], 10, 4); // seed 4 wins the final
    ($this->finish)($final);

    $ranked = collect(app(TournamentStandingsService::class)->compute($this->tournament->fresh()));
    expect($ranked->pluck('display_name')->take(4)->all())->toBe([$standings[3], $standings[1], $standings[0], $standings[2]])
        ->and($ranked->take(4)->pluck('rank')->all())->toBe([1, 2, 3, 3])
        ->and($ranked->take(4)->pluck('cut_finish')->all())->toBe(['Champion', 'Finalist', 'Top 4', 'Top 4'])
        ->and($ranked->skip(4)->pluck('rank')->all())->toBe([5, 6, 7, 8]);

    $this->actingAs($this->owner)->postJson(($this->url)('advance'))->assertStatus(422)->assertJsonPath('error', 'The final has been played — finalize the tournament.');
});

it('keeps cut games out of the Swiss standings', function () {
    $before = collect(app(TournamentStandingsService::class)->computeSwiss($this->tournament))->pluck('total_tp', 'display_name')->all();
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $round = $this->tournament->rounds()->where('is_cut', true)->firstOrFail();
    $round->update(['status' => TournamentRoundStatusEnum::Completed]);
    $round->games()->each(fn ($g) => $g->update(['player_one_vp' => 9, 'player_two_vp' => 1, 'result' => TournamentGameResultEnum::Completed]));

    expect(collect(app(TournamentStandingsService::class)->computeSwiss($this->tournament->fresh()))->pluck('total_tp', 'display_name')->all())->toBe($before);
});

it('does not let a cut game end in a tie', function () {
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $round = $this->tournament->rounds()->where('is_cut', true)->firstOrFail();
    $round->update(['status' => TournamentRoundStatusEnum::InProgress]);
    $game = $round->games()->firstOrFail();
    $url = route('tournaments.games.update', [$this->tournament, $game]);

    $this->actingAs($this->owner)->putJson($url, ['player_one_score' => ['strategy' => 3, 'scheme' => 2], 'player_two_score' => ['strategy' => 4, 'scheme' => 1]])->assertStatus(422);
    $this->actingAs($this->owner)->putJson($url, ['player_one_score' => ['strategy' => 3, 'scheme' => 2], 'player_two_score' => ['strategy' => 1, 'scheme' => 1]])->assertOk();
});

it('advances on a forfeit and sends the better seed through a tie that slipped in', function () {
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $round = $this->tournament->rounds()->where('is_cut', true)->firstOrFail();
    $games = $round->games()->get();
    $games[0]->update(['result' => TournamentGameResultEnum::Forfeited, 'is_forfeit' => true, 'forfeit_player_id' => $games[0]->player_one_id]);
    ($this->score)($games[1], 5, 5);
    $round->update(['status' => TournamentRoundStatusEnum::Completed]);

    $this->actingAs($this->owner)->postJson(($this->url)('advance'))->assertOk();
    $final = $this->tournament->rounds()->where('is_cut', true)->reorder('round_number', 'desc')->with('games.playerOne', 'games.playerTwo')->firstOrFail();

    expect([$final->games[0]->player_one_id, $final->games[0]->player_two_id])->toBe([$games[0]->player_two_id, $games[1]->player_one_id]);
});

it('blocks regenerating cut pairings and finalizing mid-cut, and drops the cut size when its rounds go', function () {
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $round = $this->tournament->rounds()->where('is_cut', true)->firstOrFail();

    $this->actingAs($this->owner)->postJson(route('tournaments.rounds.pair', [$this->tournament, $round]))->assertStatus(422);

    $round->update(['status' => TournamentRoundStatusEnum::Completed]);
    $this->actingAs($this->owner)->putJson(route('tournaments.status', $this->tournament), ['status' => 'completed'])->assertStatus(422);

    $this->actingAs($this->owner)->deleteJson(route('tournaments.rounds.delete', [$this->tournament, $round]))->assertOk();
    expect($this->tournament->fresh()->cut_size)->toBeNull();
});

it('lets a cut round start with only the bracket players paired', function () {
    $this->actingAs($this->owner)->postJson(($this->url)('start'), ['size' => 4])->assertOk();
    $round = $this->tournament->rounds()->where('is_cut', true)->firstOrFail();
    $round->update(['strategy_id' => App\Models\Strategy::factory()->create()->id, 'deployment' => App\Enums\DeploymentEnum::cases()[0], 'scheme_pool' => App\Models\Scheme::factory()->count(3)->create()->pluck('id')->all()]);

    expect(app(App\Services\TournamentStateMachine::class)->canTransitionRoundTo($round->fresh(), TournamentRoundStatusEnum::InProgress))->toBeNull();
});
