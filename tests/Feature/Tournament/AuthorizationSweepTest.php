<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentAward;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\TournamentSeries;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Calls every mutating tournament route as a signed-in user who has nothing to do with the event and expects a refusal.
 * New routes are picked up automatically, so a forgotten authorize() fails here instead of shipping.
 */
it('refuses every organizer-only action to an unrelated signed-in user', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $t = Tournament::factory()->active()->create(['creator_id' => $owner->id]);
    $a = TournamentPlayer::factory()->for($t)->create(['user_id' => User::factory()->create()->id]);
    $b = TournamentPlayer::factory()->for($t)->create();
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::InProgress]);
    $game = TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 1]);
    $award = TournamentAward::create(['tournament_id' => $t->id, 'name' => 'x']);
    $series = TournamentSeries::create(['creator_id' => $owner->id, 'name' => 'S', 'points_table' => [3], 'participation_points' => 0]);

    $params = ['tournament' => $t, 'game' => $game, 'round' => $round, 'player' => $a, 'award' => $award, 'series' => $series, 'userId' => $owner->id, 'number' => 1];
    // Open to any signed-in user by design (they act on their own behalf or create something of their own), or refused later for another reason.
    $open = [
        'tournaments.store', 'tournaments.import.store', 'tournaments.import.preview', 'tournaments.series.store',
        'tournaments.rsvp', 'tournaments.rsvp.cancel', 'tournaments.withdraw', 'tournaments.check_in', 'tournaments.check_in.cancel',
    ];

    $leaks = [];
    $checked = 0;
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        $method = collect($route->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'DELETE'], true));
        if (! str_starts_with($name, 'tournaments.') || $method === null || in_array($name, $open, true)) {
            continue;
        }
        $args = [];
        foreach ($route->parameterNames() as $p) {
            if (! isset($params[$p])) {
                continue 2; // a parameter this probe has no fixture for
            }
            $args[$p] = $params[$p];
        }

        $response = test()->actingAs($stranger)->json($method, route($name, $args), []);
        $checked++;
        if ($response->status() < 400) {
            $leaks[] = "{$method} {$route->uri()} -> {$response->status()}";
        }
    }

    expect($checked)->toBeGreaterThan(40)->and($leaks)->toBe([]);
});
