<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('round-trips strategy/scheme VP between the legacy columns and score_breakdown', function () {
    $t = Tournament::factory()->active()->create();
    $r = TournamentRound::factory()->for($t)->create();
    [$a, $b] = TournamentPlayer::factory()->for($t)->count(2)->create()->all();
    $game = TournamentGame::factory()->for($r, 'round')->create([
        'player_one_id' => $a->id, 'player_two_id' => $b->id, 'player_one_vp' => 7, 'player_two_vp' => 3,
        'score_breakdown' => ['player_one' => ['strategy' => 4, 'scheme' => 3], 'player_two' => ['strategy' => 1, 'scheme' => 2]],
    ]);
    $unscored = TournamentGame::factory()->for($r, 'round')->create(['player_one_id' => $b->id, 'player_two_id' => $a->id]);

    $migration = require database_path('migrations/2026_11_10_130000_add_score_breakdown_to_tournament_games.php');

    // Down: JSON → legacy columns.
    $migration->down();
    expect(Schema::hasColumn('tournament_games', 'score_breakdown'))->toBeFalse();
    $row = DB::table('tournament_games')->find($game->id);
    expect([$row->player_one_strategy_vp, $row->player_one_scheme_vp, $row->player_two_strategy_vp, $row->player_two_scheme_vp])->toBe([4, 3, 1, 2]);

    // Up: legacy columns → JSON, legacy columns dropped, unscored games stay null.
    $migration->up();
    expect(Schema::hasColumn('tournament_games', 'player_one_strategy_vp'))->toBeFalse();
    expect(TournamentGame::find($game->id)->breakdownFor('player_two'))->toBe(['strategy' => 1, 'scheme' => 2]);
    expect(TournamentGame::find($unscored->id)->score_breakdown)->toBeNull();
});
