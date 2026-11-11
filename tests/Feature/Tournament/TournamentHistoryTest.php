<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Tournaments\TournamentHistory;
use Inertia\Testing\AssertableInertia as Assert;

/** A finished event with one round per entry of $games: [p1 key, p2 key, p1 vp, p2 vp, extra game attributes]. */
function finishedEvent(string $name, string $date, array $players, array $games, array $attrs = []): array
{
    $t = Tournament::factory()->create(['status' => 'completed', 'name' => $name, 'event_date' => $date, ...$attrs]);
    $made = [];
    foreach ($players as $key => $attributes) {
        $made[$key] = TournamentPlayer::factory()->for($t)->create($attributes);
    }
    foreach ($games as $i => [$a, $b, $vp1, $vp2, $extra]) {
        $round = TournamentRound::factory()->for($t)->create(['round_number' => $i + 1, 'status' => 'completed']);
        TournamentGame::factory()->for($round, 'round')->create([
            'player_one_id' => $made[$a]->id, 'player_two_id' => $b ? $made[$b]->id : null,
            'player_one_vp' => $vp1, 'player_two_vp' => $vp2, 'result' => 'completed', 'player_one_faction' => $made[$a]->getRawOriginal('faction'),
            'player_two_faction' => $b ? $made[$b]->getRawOriginal('faction') : null, ...$extra,
        ]);
    }

    return [$t, $made];
}

beforeEach(function () {
    $this->me = User::factory()->create(['name' => 'Mia']);
    $this->rival = User::factory()->create(['name' => 'Rex']);
    $this->history = fn () => app(TournamentHistory::class)->forUser($this->me->fresh());
});

it('records placing, win-loss and per-faction results across finished events', function () {
    // Event 1: Mia (guild) beats Rex 8-2, then loses to a guest 3-7.
    finishedEvent('Spring Open', '2026-03-01', [
        'me' => ['display_name' => 'Mia', 'user_id' => $this->me->id, 'faction' => 'guild'],
        'rex' => ['display_name' => 'Rex', 'user_id' => $this->rival->id, 'faction' => 'bayou'],
        'guest' => ['display_name' => 'Gus', 'faction' => 'neverborn'],
    ], [['me', 'rex', 8, 2, []], ['guest', 'me', 7, 3, []]]);
    // Event 2: Mia (arcanists) ties Rex, then beats him.
    finishedEvent('Autumn Open', '2026-09-01', [
        'me' => ['display_name' => 'Mia', 'user_id' => $this->me->id, 'faction' => 'arcanists'],
        'rex' => ['display_name' => 'Rex', 'user_id' => $this->rival->id, 'faction' => 'bayou'],
    ], [['me', 'rex', 5, 5, []], ['me', 'rex', 9, 1, []]]);

    $h = ($this->history)();

    expect($h['summary'])->toMatchArray(['events' => 2, 'games' => 4, 'won' => 2, 'lost' => 1, 'tied' => 1, 'win_rate' => 50])
        ->and(collect($h['events'])->pluck('name')->all())->toBe(['Autumn Open', 'Spring Open']) // newest first
        ->and($h['events'][0])->toMatchArray(['rank' => 1, 'of' => 2, 'won' => 1, 'tied' => 1, 'lost' => 0, 'faction' => 'arcanists'])
        ->and($h['summary']['wins'])->toBe(1)->and($h['summary']['podiums'])->toBe(2)
        ->and(collect($h['by_faction'])->firstWhere('faction', 'guild'))->toMatchArray(['games' => 2, 'won' => 1, 'lost' => 1])
        ->and(collect($h['by_faction'])->firstWhere('faction', 'arcanists'))->toMatchArray(['games' => 2, 'won' => 1, 'tied' => 1])
        ->and($h['rivals'])->toHaveCount(1)
        ->and($h['rivals'][0])->toMatchArray(['name' => 'Rex', 'games' => 3, 'won' => 2, 'tied' => 1]);
});

it('ignores byes, unfinished games, running and draft events, and counts forfeits correctly', function () {
    [$t, $p] = finishedEvent('Bye Cup', '2026-04-01', [
        'me' => ['display_name' => 'Mia', 'user_id' => $this->me->id],
        'other' => ['display_name' => 'Oz'],
    ], [['me', null, null, null, ['is_bye' => true]], ['me', 'other', null, null, ['is_forfeit' => true, 'result' => 'forfeited']]]);
    TournamentGame::query()->where('is_forfeit', true)->update(['forfeit_player_id' => $p['other']->id]);

    $running = Tournament::factory()->active()->create();
    TournamentPlayer::factory()->for($running)->create(['user_id' => $this->me->id]);
    $draft = Tournament::factory()->create(['status' => 'draft']);
    TournamentPlayer::factory()->for($draft)->create(['user_id' => $this->me->id]);

    $h = ($this->history)();

    expect($h['summary'])->toMatchArray(['events' => 1, 'games' => 1, 'won' => 1, 'lost' => 0]);
});

it('counts a team event through its members', function () {
    [$t, $p] = finishedEvent('Team Cup', '2026-05-01', [
        'team' => ['display_name' => 'Alpha Squad', 'faction' => null],
        'them' => ['display_name' => 'Bravo Crew', 'faction' => null],
    ], [['team', 'them', 9, 4, []]], ['team_size' => 2]);
    $p['team']->members()->create(['display_name' => 'Mia', 'user_id' => $this->me->id, 'position' => 0]);

    $event = ($this->history)()['events'][0];

    expect($event)->toMatchArray(['is_team' => true, 'team_name' => 'Alpha Squad', 'rank' => 1, 'won' => 1]);
});

it('is empty for someone who has never played', function () {
    $h = ($this->history)();

    expect($h['events'])->toBe([])->and($h['summary'])->toMatchArray(['events' => 0, 'games' => 0, 'win_rate' => null, 'best_finish' => null]);
});

it('only lists a rival you met at least twice', function () {
    finishedEvent('One Game', '2026-06-01', [
        'me' => ['display_name' => 'Mia', 'user_id' => $this->me->id],
        'rex' => ['display_name' => 'Rex', 'user_id' => $this->rival->id],
    ], [['me', 'rex', 6, 4, []]]);

    expect(($this->history)()['rivals'])->toBe([]);
});

it('is on the public stats page', function () {
    finishedEvent('Spring Open', '2026-03-01', [
        'me' => ['display_name' => 'Mia', 'user_id' => $this->me->id],
        'x' => ['display_name' => 'Xan'],
    ], [['me', 'x', 8, 2, []]]);

    $this->get(route('stats.show', $this->me->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tournament_history.summary.events', 1)
        ->where('tournament_history.events.0.name', 'Spring Open'));

    $this->get(route('stats.show', $this->rival->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->where('tournament_history.summary.events', 0));
});
