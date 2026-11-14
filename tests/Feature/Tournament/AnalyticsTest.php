<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Services\TournamentStandingsService;
use App\Tournaments\Analytics\EventInsights;
use App\Tournaments\Analytics\OrganizerInsights;
use Inertia\Testing\AssertableInertia as Assert;

/** One round per entry of $games: [p1, p2, p1 vp, p2 vp, extra game attributes]. */
function analyticsEvent(User $owner, string $date, array $players, array $games, array $attrs = []): array
{
    $t = Tournament::factory()->completed()->create(['creator_id' => $owner->id, 'event_date' => $date, ...$attrs]);
    $t->organizers()->attach($owner->id);
    $made = [];
    foreach ($players as $key => $attributes) {
        $made[$key] = TournamentPlayer::factory()->for($t)->create($attributes);
    }
    foreach ($games as $i => [$a, $b, $vp1, $vp2, $extra]) {
        $round = TournamentRound::factory()->for($t)->create(['round_number' => $i + 1, 'status' => 'completed']);
        TournamentGame::factory()->for($round, 'round')->create([
            'player_one_id' => $made[$a]->id, 'player_two_id' => $b ? $made[$b]->id : null,
            'player_one_vp' => $vp1, 'player_two_vp' => $vp2, 'result' => 'completed',
            'player_one_faction' => $made[$a]->getRawOriginal('faction'),
            'player_two_faction' => $b ? $made[$b]->getRawOriginal('faction') : null,
            'is_bye' => false, 'is_forfeit' => false, ...$extra,
        ]);
    }

    return [$t->fresh(), $made];
}

beforeEach(function () {
    $this->owner = User::factory()->create();
});

it('reads one event: scores, margins, forfeits and byes', function () {
    [$t] = analyticsEvent($this->owner, '2026-05-01', [
        'a' => ['display_name' => 'Ann', 'faction' => 'guild'],
        'b' => ['display_name' => 'Bo', 'faction' => 'bayou'],
        'c' => ['display_name' => 'Cy', 'faction' => 'guild'],
    ], [
        ['a', 'b', 9, 3, []],      // margin 6
        ['b', 'c', 5, 5, []],      // tie
        ['c', 'a', 7, 6, []],      // close
        ['a', null, 0, 0, ['is_bye' => true, 'player_two_id' => null]],
        ['b', 'c', 0, 0, ['is_forfeit' => true, 'result' => 'forfeited']],
    ]);

    $insights = app(EventInsights::class)->forEvent($t, app(TournamentStandingsService::class)->compute($t));

    expect($insights['games']['contested'])->toBe(3)
        ->and($insights['games']['byes'])->toBe(1)
        ->and($insights['games']['forfeits'])->toBe(1)
        ->and($insights['games']['ties'])->toBe(1)
        ->and($insights['games']['close'])->toBe(2)
        ->and($insights['games']['avg_vp'])->toBe(5.8)
        ->and($insights['games']['avg_margin'])->toBe(2.3)
        ->and(collect($insights['games']['margin_buckets'])->pluck('count')->all())->toBe([1, 1, 0, 1, 0])
        ->and($insights['highlights']['biggest_win'])->toMatchArray(['winner' => 'Ann', 'loser' => 'Bo', 'margin' => 6])
        ->and($insights['highlights']['high_score']['vp'])->toBe(9)
        ->and($insights['rounds'])->toHaveCount(5);
});

it('breaks results down by faction with ties as half a win', function () {
    [$t] = analyticsEvent($this->owner, '2026-05-01', [
        'a' => ['display_name' => 'Ann', 'faction' => 'guild'],
        'b' => ['display_name' => 'Bo', 'faction' => 'bayou'],
    ], [['a', 'b', 9, 3, []], ['a', 'b', 4, 4, []]]);

    $factions = collect(app(EventInsights::class)->forEvent($t, [])['factions'])->keyBy('label');

    expect($factions['Guild'])->toMatchArray(['games' => 2, 'wins' => 1, 'ties' => 1, 'losses' => 0, 'win_rate' => 75.0])
        ->and($factions['Bayou'])->toMatchArray(['games' => 2, 'wins' => 0, 'ties' => 1, 'losses' => 1, 'win_rate' => 25.0]);
});

it('tells new players from returning ones by account', function () {
    $regular = User::factory()->create();
    $fresh = User::factory()->create();
    analyticsEvent($this->owner, '2026-01-01', ['r' => ['display_name' => 'Reg', 'user_id' => $regular->id]], []);
    [$t] = analyticsEvent($this->owner, '2026-06-01', [
        'r' => ['display_name' => 'Reg', 'user_id' => $regular->id],
        'f' => ['display_name' => 'Fresh', 'user_id' => $fresh->id],
        'g' => ['display_name' => 'Guest'],
    ], []);

    $field = app(EventInsights::class)->forEvent($t, [])['field'];

    expect($field)->toMatchArray(['registered' => 3, 'with_accounts' => 2, 'returning' => 1, 'new' => 1]);
});

it('adds an insights prop to Manage only once a result exists', function () {
    $pending = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $pending->organizers()->attach($this->owner->id);
    $this->actingAs($this->owner)->get(route('tournaments.manage', $pending))
        ->assertInertia(fn (Assert $page) => $page->where('insights', null));

    [$t] = analyticsEvent($this->owner, '2026-05-01', [
        'a' => ['display_name' => 'Ann', 'faction' => 'guild'], 'b' => ['display_name' => 'Bo', 'faction' => 'bayou'],
    ], [['a', 'b', 8, 2, []]]);

    $this->actingAs($this->owner)->get(route('tournaments.manage', $t))
        ->assertInertia(fn (Assert $page) => $page->component('Tournaments/Manage')->missing('insights'));

    // Deferred: a follow-up partial request fills it in.
    $this->actingAs($this->owner)->get(route('tournaments.manage', $t), [
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Tournaments/Manage', 'X-Inertia-Partial-Data' => 'insights',
        'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
    ])->assertOk()->assertJsonPath('props.insights.games.contested', 1);
});

it('keeps the event insights away from people who cannot manage the event', function () {
    [$t] = analyticsEvent($this->owner, '2026-05-01', ['a' => ['display_name' => 'Ann'], 'b' => ['display_name' => 'Bo']], [['a', 'b', 8, 2, []]]);

    $this->actingAs(User::factory()->create())->get(route('tournaments.manage', $t))->assertForbidden();
});

it('summarises every event an organizer runs: turnout, repeat players and regulars', function () {
    $x = User::factory()->create();
    $y = User::factory()->create();
    analyticsEvent($this->owner, '2026-01-01', [
        'x' => ['display_name' => 'Xan', 'user_id' => $x->id, 'faction' => 'guild'],
        'y' => ['display_name' => 'Yo', 'user_id' => $y->id, 'faction' => 'bayou'],
    ], [['x', 'y', 8, 2, []]]);
    analyticsEvent($this->owner, '2026-03-01', [
        'x' => ['display_name' => 'Xan', 'user_id' => $x->id, 'faction' => 'guild'],
        'z' => ['display_name' => 'Zed', 'faction' => 'bayou'],
        'w' => ['display_name' => 'Walt', 'faction' => 'guild'],
    ], [['x', 'z', 5, 5, []]]);
    // Someone else's event and an unfinished one never show up.
    analyticsEvent(User::factory()->create(), '2026-02-01', ['p' => ['display_name' => 'Pat']], []);
    Tournament::factory()->create(['creator_id' => $this->owner->id, 'status' => 'registration']);

    $out = app(OrganizerInsights::class)->forUser($this->owner->id);

    expect($out['summary'])->toMatchArray(['events' => 2, 'entries' => 5, 'unique_players' => 4, 'repeat_players' => 1, 'repeat_pct' => 25, 'largest' => 3, 'avg_attendance' => 2.5])
        ->and($out['summary']['returning_pct'])->toBe(33)
        ->and(collect($out['events'])->pluck('name')->count())->toBe(2)
        ->and($out['events'][1])->toMatchArray(['players' => 3, 'returning' => 1, 'new' => 2])
        ->and($out['regulars'])->toBe([['name' => 'Xan', 'events' => 2]])
        ->and(collect($out['factions'])->firstWhere('label', 'Guild')['games'])->toBe(2);
});

it('limits the organizer view to the last twelve months on request', function () {
    analyticsEvent($this->owner, now()->subYears(2)->toDateString(), ['a' => ['display_name' => 'Old']], []);
    analyticsEvent($this->owner, now()->subMonth()->toDateString(), ['a' => ['display_name' => 'New']], []);

    expect(app(OrganizerInsights::class)->forUser($this->owner->id)['summary']['events'])->toBe(2)
        ->and(app(OrganizerInsights::class)->forUser($this->owner->id, '12m')['summary']['events'])->toBe(1)
        ->and(app(OrganizerInsights::class)->forUser($this->owner->id, 'nonsense')['range'])->toBe('all');
});

it('renders the organizer analytics page for a signed-in user and not a guest', function () {
    $this->get(route('tournaments.analytics'))->assertRedirect(route('login'));

    $this->actingAs($this->owner)->get(route('tournaments.analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tournaments/Analytics')->where('summary.events', 0));
});
