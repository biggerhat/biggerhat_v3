<?php

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Support\Stats\GameAnalytics;
use App\Support\Stats\PlayedGames;
use Inertia\Testing\AssertableInertia as Assert;

/** A game played through the tracker: me in slot 1, a real opponent in slot 2. */
function trackedGame(User $me, string $result, int $vp = 6, int $oppVp = 3, array $over = []): Game
{
    $game = Game::factory()->completed()->create([
        'creator_id' => $me->id,
        'winner_id' => $result === 'win' ? $me->id : null,
        'winner_slot' => $result === 'tie' ? null : ($result === 'win' ? 1 : 2),
        'is_tie' => $result === 'tie',
        'completed_at' => now()->subDays(3),
        ...$over,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $me->id, 'slot' => 1, 'faction' => 'guild', 'master_name' => 'Lady Justice', 'total_points' => $vp]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => User::factory(), 'slot' => 2, 'faction' => 'arcanists', 'master_name' => 'Rasputina', 'total_points' => $oppVp]);

    return $game;
}

function loggedGame(User $me, string $result, array $over = [], int $vp = 7, int $oppVp = 2): Game
{
    $game = Game::factory()->logged()->create([
        'creator_id' => $me->id,
        'winner_id' => $result === 'win' ? $me->id : null,
        'winner_slot' => $result === 'tie' ? null : ($result === 'win' ? 1 : 2),
        'is_tie' => $result === 'tie',
        ...$over,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $me->id, 'slot' => 1, 'faction' => 'bayou', 'master_name' => 'Som\'er Teeth Jones', 'total_points' => $vp]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => null, 'opponent_name' => 'Sam', 'slot' => 2, 'faction' => 'ten_thunders', 'master_name' => 'Yan Lo', 'total_points' => $oppVp]);

    return $game;
}

it('puts tracker, logged and tournament games in one list, with the source of each', function () {
    $me = User::factory()->create();
    trackedGame($me, 'win');
    loggedGame($me, 'loss');

    $event = Tournament::factory()->create(['status' => 'completed', 'event_date' => '2026-02-01']);
    $mine = TournamentPlayer::factory()->for($event)->create(['user_id' => $me->id, 'faction' => 'guild']);
    $other = TournamentPlayer::factory()->for($event)->create(['faction' => 'arcanists']);
    $round = TournamentRound::factory()->for($event)->create(['round_number' => 1, 'status' => 'completed']);
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $mine->id, 'player_two_id' => $other->id, 'player_one_vp' => 8, 'player_two_vp' => 8, 'result' => 'completed', 'player_one_faction' => 'guild', 'player_two_faction' => 'arcanists']);

    $games = app(PlayedGames::class)->for($me, true);

    expect($games)->toHaveCount(3)
        ->and($games->pluck('source')->sort()->values()->all())->toBe(['logged', 'tournament', 'tracked'])
        ->and($games->firstWhere('source', 'tournament')->result)->toBe('tie')
        ->and($games->firstWhere('source', 'logged')->oppName)->toBe('Sam')
        ->and($games->firstWhere('source', 'logged')->result)->toBe('loss')
        ->and($games->firstWhere('source', 'tracked')->result)->toBe('win');
});

it('counts a tournament round played through the tracker once, as the tracker game', function () {
    $me = User::factory()->create();
    $game = trackedGame($me, 'win');
    $event = Tournament::factory()->create(['status' => 'completed', 'event_date' => '2026-02-01']);
    $mine = TournamentPlayer::factory()->for($event)->create(['user_id' => $me->id]);
    $other = TournamentPlayer::factory()->for($event)->create();
    $round = TournamentRound::factory()->for($event)->create(['round_number' => 1, 'status' => 'completed']);
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $mine->id, 'player_two_id' => $other->id, 'player_one_vp' => 6, 'player_two_vp' => 3, 'result' => 'completed', 'game_id' => $game->id]);

    expect(app(PlayedGames::class)->for($me, true))->toHaveCount(1);
});

it('keeps a private logged game to its owner and shows a public one to everyone', function () {
    $me = User::factory()->create();
    loggedGame($me, 'win', ['is_public' => false]);
    loggedGame($me, 'win', ['is_public' => true]);
    trackedGame($me, 'win');

    $played = app(PlayedGames::class);
    expect($played->for($me, true))->toHaveCount(3)->and($played->for($me, false))->toHaveCount(2);

    $this->actingAs(User::factory()->create())->get(route('stats.analytics', $me->slug))->assertInertia(fn (Assert $page) => $page->where('analytics.overview.games', 2));
    $this->actingAs($me)->get(route('stats.analytics', $me->slug))->assertInertia(fn (Assert $page) => $page->where('analytics.overview.games', 3));
});

it('counts a confirmed link in the opponent\'s own record, from their side', function () {
    $me = User::factory()->create();
    $sam = User::factory()->create();
    $game = loggedGame($me, 'loss', ['is_public' => true]);
    $game->players()->where('slot', 2)->update(['user_id' => $sam->id, 'linked_user_id' => $sam->id, 'link_status' => 'confirmed']);

    $theirs = app(PlayedGames::class)->for($sam, true);
    expect($theirs)->toHaveCount(1)->and($theirs[0]->result)->toBe('win')->and($theirs[0]->oppName)->toBe($me->name);
});

it('works out the record, win rate interval and streaks', function () {
    $me = User::factory()->create();
    foreach (['win', 'win', 'loss', 'win', 'win', 'win', 'tie'] as $i => $r) {
        trackedGame($me, $r, 6, 3, ['completed_at' => now()->subDays(20 - $i)]);
    }

    $o = app(GameAnalytics::class)->compute(app(PlayedGames::class)->for($me, true))['overview'];

    expect($o['games'])->toBe(7)->and($o['wins'])->toBe(5)->and($o['losses'])->toBe(1)->and($o['ties'])->toBe(1)
        ->and($o['win_rate'])->toBe(71)->and($o['reliable'])->toBeTrue()
        ->and($o['interval']['low'])->toBeLessThan(71)->and($o['interval']['high'])->toBeGreaterThan(71)
        ->and($o['best_win_streak'])->toBe(3)
        ->and($o['current_streak'])->toBe(['type' => 'tie', 'count' => 1])
        ->and($o['avg_vp'])->toBe(6.0)->and($o['avg_margin'])->toBe(3.0);
});

it('does not call a rate reliable on fewer than five games', function () {
    $me = User::factory()->create();
    trackedGame($me, 'win');
    trackedGame($me, 'win');

    $a = app(GameAnalytics::class)->compute(app(PlayedGames::class)->for($me, true));

    expect($a['overview']['reliable'])->toBeFalse()->and($a['by_faction'][0]['reliable'])->toBeFalse();
});

it('groups by faction, master and matchup, and leaves out games that lack the data', function () {
    $me = User::factory()->create();
    trackedGame($me, 'win');
    trackedGame($me, 'loss');
    loggedGame($me, 'win');
    $noMaster = loggedGame($me, 'win');
    $noMaster->players()->where('slot', 1)->update(['master_name' => null]);

    $a = app(GameAnalytics::class)->compute(app(PlayedGames::class)->for($me, true));

    expect(collect($a['by_faction'])->firstWhere('key', 'guild')['games'])->toBe(2)
        ->and(collect($a['by_faction'])->firstWhere('key', 'bayou')['games'])->toBe(2)
        ->and(collect($a['by_master'])->firstWhere('key', 'Lady Justice')['games'])->toBe(2)
        ->and(collect($a['by_master'])->sum('games'))->toBe(3)
        ->and($a['coverage'])->toMatchArray(['games' => 4, 'with_master' => 3])
        ->and(collect($a['matchups'])->firstWhere('faction', 'guild')['opponent_faction'])->toBe('arcanists')
        ->and(collect($a['opponents'])->firstWhere('name', 'Sam')['games'])->toBe(2);
});

it('counts a solo game by default and leaves it out when asked', function () {
    $me = User::factory()->create();
    trackedGame($me, 'win');
    trackedGame($me, 'win', 6, 3, ['is_solo' => true]);

    $games = app(PlayedGames::class)->for($me, true);
    $analytics = app(GameAnalytics::class);

    expect($analytics->filter($games, [])->count())->toBe(2)
        ->and($analytics->filter($games, ['solo' => true])->count())->toBe(2)
        ->and($analytics->filter($games, ['solo' => false])->count())->toBe(1);
    $this->get(route('stats.analytics', ['user' => $me->slug, 'solo' => 0]))->assertInertia(fn (Assert $page) => $page->where('analytics.overview.games', 1));
});

it('reads schemes, turn points and models from the games that have them', function () {
    $me = User::factory()->create();
    $game = trackedGame($me, 'win');
    $mine = $game->players()->where('slot', 1)->first();
    $theirs = $game->players()->where('slot', 2)->first();
    $scheme = App\Models\Scheme::factory()->create(['name' => 'Bodyguard']);
    GameTurn::create(['game_id' => $game->id, 'game_player_id' => $mine->id, 'turn_number' => 1, 'scheme_id' => $scheme->id, 'scheme_action' => 'scored', 'strategy_points' => 2, 'scheme_points' => 3, 'points_scored' => 5]);
    GameTurn::create(['game_id' => $game->id, 'game_player_id' => $theirs->id, 'turn_number' => 1, 'strategy_points' => 1, 'scheme_points' => 0, 'points_scored' => 1]);
    App\Models\GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $mine->id, 'display_name' => 'Judge', 'is_killed' => true]);
    trackedGame($me, 'loss'); // no turns, no crew

    $a = app(GameAnalytics::class)->compute(app(PlayedGames::class)->for($me, true));

    expect($a['schemes'][0])->toMatchArray(['name' => 'Bodyguard', 'picked' => 1, 'scored' => 1, 'total_vp' => 3])
        ->and($a['turns']['by_turn'][0])->toMatchArray(['turn' => 1, 'strategy' => 2.0, 'scheme' => 3.0, 'opp_strategy' => 1.0])
        ->and($a['turns']['turn_one_lead']['games'])->toBe(1)
        ->and($a['models'][0])->toMatchArray(['name' => 'Judge', 'hired' => 1, 'killed' => 1])
        ->and($a['coverage'])->toMatchArray(['games' => 2, 'with_turns' => 1, 'with_models' => 1]);
});

it('filters by date, source, faction and result', function () {
    $me = User::factory()->create();
    trackedGame($me, 'win', 6, 3, ['completed_at' => now()->subDays(30)]);
    loggedGame($me, 'loss', ['completed_at' => now()->subDays(2)]);

    $games = app(PlayedGames::class)->for($me, true);
    $a = app(GameAnalytics::class);

    expect($a->filter($games, ['from' => now()->subDays(10)->toDateString()]))->toHaveCount(1)
        ->and($a->filter($games, ['sources' => ['tracked']]))->toHaveCount(1)
        ->and($a->filter($games, ['faction' => 'bayou']))->toHaveCount(1)
        ->and($a->filter($games, ['result' => 'win']))->toHaveCount(1)
        ->and($a->filter($games, ['opponent' => 'sam']))->toHaveCount(1)
        ->and($a->filter($games, ['faction' => 'bayou', 'result' => 'win']))->toHaveCount(0);
});

it('serves the analytics page to a guest, rejects bad filters and downloads the games as CSV', function () {
    $me = User::factory()->create();
    trackedGame($me, 'win');
    loggedGame($me, 'win', ['is_public' => true, 'venue' => '=HYPERLINK("x")']);

    $this->get(route('stats.analytics', $me->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Profile/Analytics')->where('analytics.overview.games', 2));
    $this->get(route('stats.analytics', ['user' => $me->slug, 'result' => 'maybe']))->assertSessionHasErrors('result');
    $this->get(route('stats.analytics', ['user' => $me->slug, 'from' => '2026-05-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');

    $csv = $this->get(route('stats.analytics_csv', $me->slug))->assertOk()->streamedContent();
    expect($csv)->toContain('Date,Source,Result')->toContain('Lady Justice')->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

it('computes the Wilson interval sensibly', function () {
    $a = app(GameAnalytics::class);

    expect($a->wilson(0, 0))->toBeNull()
        ->and($a->wilson(5, 10))->toBe(['low' => 24, 'high' => 76])
        ->and($a->wilson(10, 10)['high'])->toBe(100)
        ->and($a->wilson(0, 10)['low'])->toBe(0);
});
