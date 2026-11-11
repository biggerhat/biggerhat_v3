<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;

function scoredGameFor(string $ruleset): array
{
    $owner = User::factory()->create();
    $t = Tournament::factory()->active()->create(['creator_id' => $owner->id, 'ruleset' => $ruleset]);
    $round = TournamentRound::factory()->for($t)->inProgress()->create();
    $p1 = TournamentPlayer::factory()->for($t)->create();
    $p2 = TournamentPlayer::factory()->for($t)->create();
    $game = TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $p1->id, 'player_two_id' => $p2->id]);

    return [$owner, $t, $game];
}

it('scores a Gaining Grounds game from strategy + scheme components', function () {
    [$owner, $t, $game] = scoredGameFor('malifaux_gaining_grounds');

    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [
        'player_one_score' => ['strategy' => 4, 'scheme' => 3],
        'player_two_score' => ['strategy' => 1, 'scheme' => 2],
    ])->assertOk();

    $game->refresh();
    expect($game->player_one_vp)->toBe(7)->and($game->player_two_vp)->toBe(3);
    expect($game->breakdownFor('player_one'))->toBe(['strategy' => 4, 'scheme' => 3]);
});

it('rejects a missing or out-of-range Gaining Grounds component', function () {
    [$owner, $t, $game] = scoredGameFor('malifaux_gaining_grounds');

    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [
        'player_one_score' => ['strategy' => 6, 'scheme' => 3],
        'player_two_score' => ['strategy' => 1],
    ])->assertStatus(422)->assertJsonValidationErrors(['player_one_score.strategy', 'player_two_score.scheme']);
});

it('scores a Fields of Glory game from a single VP total', function () {
    [$owner, $t, $game] = scoredGameFor('tos_fields_of_glory');

    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [
        'player_one_score' => ['vp' => 8],
        'player_two_score' => ['vp' => 4],
    ])->assertOk();

    expect($game->fresh())->player_one_vp->toBe(8)->player_two_vp->toBe(4);
});

it('rejects Gaining Grounds components on a Fields of Glory game', function () {
    [$owner, $t, $game] = scoredGameFor('tos_fields_of_glory');

    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [
        'player_one_score' => ['strategy' => 4, 'scheme' => 3],
        'player_two_score' => ['strategy' => 1, 'scheme' => 2],
    ])->assertStatus(422)->assertJsonValidationErrors(['player_one_score.vp']);
});

it('exports ruleset-specific score columns in the CSV', function () {
    [$owner, $t, $game] = scoredGameFor('malifaux_gaining_grounds');
    $game->update(['score_breakdown' => ['player_one' => ['strategy' => 4, 'scheme' => 3], 'player_two' => ['strategy' => 1, 'scheme' => 2]], 'player_one_vp' => 7, 'player_two_vp' => 3]);

    $csv = $this->actingAs($owner)->get(route('tournaments.export_csv', $t))->streamedContent();

    expect($csv)->toContain('player_one_strategy_vp', 'player_one_scheme_vp', 'player_two_scheme_vp');
});

it('records an agreed result: scored like a normal game but flagged as agreed', function () {
    [$owner, $t, $game] = scoredGameFor('malifaux_gaining_grounds');

    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [
        'player_one_score' => ['strategy' => 3, 'scheme' => 2],
        'player_two_score' => ['strategy' => 1, 'scheme' => 0],
        'agreed' => true,
    ])->assertOk();

    expect($game->fresh()->result)->toBe(\App\Enums\TournamentGameResultEnum::Agreed);

    $row = collect(app(\App\Services\TournamentStandingsService::class)->compute($t))->firstWhere('player_id', $game->player_one_id);
    expect($row)->total_tp->toBe(3)->total_vp->toBe(5)->total_diff->toBe(4);
});

it('lets the TO flip an agreed result back to a normal one, and the round can still end', function () {
    [$owner, $t, $game] = scoredGameFor('malifaux_gaining_grounds');
    $payload = ['player_one_score' => ['strategy' => 3, 'scheme' => 2], 'player_two_score' => ['strategy' => 1, 'scheme' => 0]];

    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [...$payload, 'agreed' => true])->assertOk();
    $this->actingAs($owner)->putJson(route('tournaments.games.update', [$t, $game]), [...$payload, 'agreed' => false])->assertOk();

    expect($game->fresh()->result)->toBe(\App\Enums\TournamentGameResultEnum::Completed);
});
