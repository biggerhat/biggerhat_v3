<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\TournamentSeries;
use App\Models\User;
use App\Tournaments\SeriesStandings;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished one-round event. The list is paired 0-1, 2-3, ... and the earlier player of each pair wins, so the final order is
 * list[0], list[2], list[4]..., then the losers from the last pair back to the first.
 */
function seriesEvent(TournamentSeries $series, string $name, string $date, array $order, ?User $creator = null): Tournament
{
    $t = Tournament::factory()->completed()->create(['creator_id' => ($creator ?? User::factory()->create())->id, 'series_id' => $series->id, 'name' => $name, 'event_date' => $date, 'planned_rounds' => 1]);
    $round = TournamentRound::factory()->for($t)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::Completed]);
    $players = [];
    foreach ($order as $entry) {
        [$display, $user] = is_array($entry) ? $entry : [$entry, null];
        $players[] = TournamentPlayer::factory()->for($t)->create(['display_name' => $display, 'user_id' => $user?->id]);
    }
    for ($i = 0; $i + 1 < count($players); $i += 2) {
        TournamentGame::factory()->for($round, 'round')->create([
            'player_one_id' => $players[$i]->id, 'player_two_id' => $players[$i + 1]->id, 'table_number' => $i / 2 + 1,
            'result' => 'completed', 'player_one_vp' => 10 - $i, 'player_two_vp' => 1,
        ]);
    }

    return $t;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->series = TournamentSeries::create(['creator_id' => $this->owner->id, 'name' => 'Winter League', 'points_table' => [10, 6, 3], 'participation_points' => 1, 'best_of' => null]);
});

it('adds finishing places across events into one leaderboard', function () {
    seriesEvent($this->series, 'Round One', '2026-01-10', ['Alice', 'Dave', 'Bob', 'Carol']); // Alice, Bob, Carol, Dave
    seriesEvent($this->series, 'Round Two', '2026-02-10', ['Bob', 'Frank', 'Alice', 'Erin']); // Bob, Alice, Erin, Frank

    $board = app(SeriesStandings::class)->compute($this->series);
    $by = collect($board['rows'])->keyBy('name');

    expect($board['events'])->toHaveCount(2)
        ->and($by['Alice']['total'])->toBe(16)->and($by['Bob']['total'])->toBe(16)
        ->and($by['Carol']['total'])->toBe(3)->and($by['Dave']['total'])->toBe(1)
        ->and($by['Alice']['events_played'])->toBe(2)->and($by['Alice']['wins'])->toBe(1);
});

it('counts only the best N events when the series says so', function () {
    $this->series->update(['best_of' => 1]);
    seriesEvent($this->series, 'A', '2026-01-10', ['Alice', 'Dave', 'Bob', 'Carol']); // Alice 1st
    seriesEvent($this->series, 'B', '2026-02-10', ['Bob', 'Dave', 'Alice', 'Carol']); // Alice 2nd

    $alice = collect(app(SeriesStandings::class)->compute($this->series)['rows'])->firstWhere('name', 'Alice');

    expect($alice['total'])->toBe(10)->and(array_column($alice['results'], 'counts'))->toBe([true, false]);
});

it('gives participation points to anyone who played but placed outside the table', function () {
    seriesEvent($this->series, 'Big', '2026-01-10', ['A', 'B', 'C', 'D', 'E', 'F']);

    $rows = collect(app(SeriesStandings::class)->compute($this->series)['rows'])->keyBy('name');

    expect($rows['A']['total'])->toBe(10)->and($rows['D']['total'])->toBe(1)->and($rows['F']['total'])->toBe(1);
});

it('treats one account as one person across events, and joins a plain name to it', function () {
    $zed = User::factory()->create(['name' => 'Zed']);
    seriesEvent($this->series, 'A', '2026-01-10', [['Zed', $zed], 'Bob', 'Carol', 'Dave']);
    seriesEvent($this->series, 'B', '2026-02-10', [['Zedd (renamed)', $zed], 'Bob', 'Carol', 'Dave']);
    seriesEvent($this->series, 'C', '2026-03-10', ['zed', 'Bob', 'Carol', 'Dave']); // an unlinked entry with the same name

    $rows = collect(app(SeriesStandings::class)->compute($this->series)['rows']);
    $zedRow = $rows->firstWhere('user_id', $zed->id);

    expect($zedRow['events_played'])->toBe(3)->and($rows->where('name', 'Zed')->count() + $rows->where('name', 'zed')->count())->toBe(1);
});

it('ranks equal totals jointly and ignores ringers, unfinished and team events', function () {
    seriesEvent($this->series, 'A', '2026-01-10', ['Alice', 'Bob']);
    $draft = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'series_id' => $this->series->id]);
    TournamentPlayer::factory()->for($draft)->create(['display_name' => 'Ghost']);
    $team = seriesEvent($this->series, 'Teams', '2026-02-10', ['T1', 'T2']);
    $team->update(['team_size' => 2]);

    $board = app(SeriesStandings::class)->compute($this->series);

    expect($board['events'])->toHaveCount(1)->and(collect($board['rows'])->pluck('name')->all())->toBe(['Alice', 'Bob'])
        ->and(collect($board['rows'])->pluck('rank')->all())->toBe([1, 2]);
});

it('shows the leaderboard and the events on the public series page', function () {
    seriesEvent($this->series, 'Round One', '2026-01-10', ['Alice', 'Bob', 'Carol', 'Dave']);

    $this->get(route('tournaments.series.show', $this->series))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Tournaments/Series/Show')
        ->where('series.name', 'Winter League')->where('standings.0.name', 'Alice')->where('standings.0.total', 10)->has('events', 1)->where('can_manage', false));
    $this->get(route('tournaments.series.index'))->assertInertia(fn (Assert $page) => $page->component('Tournaments/Series/Index')->where('series.data.0.name', 'Winter League')->where('series.data.0.finished', 1));
});

it('lets an organizer create, edit and delete a series, and nobody else', function () {
    $payload = ['name' => 'Spring Ladder', 'description' => 'Monthly', 'points' => [5, 3, 1], 'participation_points' => 0, 'best_of' => 3];

    $this->actingAs($this->owner)->post(route('tournaments.series.store'), $payload)->assertRedirect();
    $mine = TournamentSeries::where('name', 'Spring Ladder')->firstOrFail();
    expect($mine->points_table)->toBe([5, 3, 1])->and($mine->best_of)->toBe(3)->and($mine->creator_id)->toBe($this->owner->id);

    $this->actingAs($this->owner)->put(route('tournaments.series.update', $mine), [...$payload, 'name' => 'Spring Ladder 2'])->assertRedirect();
    expect($mine->fresh()->name)->toBe('Spring Ladder 2');

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->put(route('tournaments.series.update', $mine), $payload)->assertForbidden();
    $this->actingAs($stranger)->get(route('tournaments.series.edit', $mine))->assertForbidden();
    $this->actingAs($stranger)->delete(route('tournaments.series.destroy', $mine))->assertForbidden();

    $this->actingAs($this->owner)->get(route('tournaments.series.show', $mine))->assertInertia(fn (Assert $page) => $page->where('can_manage', true));
    $this->actingAs($this->owner)->delete(route('tournaments.series.destroy', $mine))->assertRedirect(route('tournaments.series.index'));
    expect(TournamentSeries::find($mine->id))->toBeNull();
});

it('validates the series settings', function () {
    $ok = ['name' => 'X', 'points' => [3, 2, 1], 'participation_points' => 1];

    $this->actingAs($this->owner)->postJson(route('tournaments.series.store'), [...$ok, 'name' => ''])->assertJsonValidationErrors('name');
    $this->actingAs($this->owner)->postJson(route('tournaments.series.store'), [...$ok, 'points' => []])->assertJsonValidationErrors('points');
    $this->actingAs($this->owner)->postJson(route('tournaments.series.store'), [...$ok, 'points' => [3, 'a']])->assertJsonValidationErrors('points.1');
    $this->actingAs($this->owner)->postJson(route('tournaments.series.store'), [...$ok, 'points' => range(1, 17)])->assertJsonValidationErrors('points');
    $this->actingAs($this->owner)->postJson(route('tournaments.series.store'), [...$ok, 'best_of' => 0])->assertJsonValidationErrors('best_of');
});

it('puts an event into the creator\'s series and takes it out again', function () {
    $event = Tournament::factory()->create(['creator_id' => $this->owner->id]);
    $url = route('tournaments.series.assign', $event);

    $this->actingAs($this->owner)->putJson($url, ['series' => $this->series->uuid])->assertOk();
    expect($event->fresh()->series_id)->toBe($this->series->id);
    $this->actingAs($this->owner)->get(route('tournaments.manage', $event))->assertInertia(fn (Assert $page) => $page->where('series.uuid', $this->series->uuid)->where('my_series.0.name', 'Winter League'));
    $this->get(route('tournaments.view', $event))->assertInertia(fn (Assert $page) => $page->where('series.name', 'Winter League'));

    $this->actingAs($this->owner)->putJson($url, ['series' => null])->assertOk();
    expect($event->fresh()->series_id)->toBeNull();
});

it('refuses to attach to someone else\'s series, team events and strangers', function () {
    $event = Tournament::factory()->create(['creator_id' => $this->owner->id]);
    $other = User::factory()->create();
    $theirs = TournamentSeries::create(['creator_id' => $other->id, 'name' => 'Theirs', 'points_table' => [3], 'participation_points' => 0]);

    $this->actingAs($this->owner)->putJson(route('tournaments.series.assign', $event), ['series' => $theirs->uuid])->assertForbidden();
    $this->actingAs($other)->putJson(route('tournaments.series.assign', $event), ['series' => $theirs->uuid])->assertForbidden();

    $team = Tournament::factory()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
    $this->actingAs($this->owner)->putJson(route('tournaments.series.assign', $team), ['series' => $this->series->uuid])->assertStatus(422);
    $this->actingAs($this->owner)->putJson(route('tournaments.series.assign', $event), ['series' => 'not-a-uuid'])->assertJsonValidationErrors('series');
});

it('keeps events when their series is deleted', function () {
    $event = Tournament::factory()->create(['creator_id' => $this->owner->id, 'series_id' => $this->series->id]);

    $this->actingAs($this->owner)->delete(route('tournaments.series.destroy', $this->series))->assertRedirect();

    expect($event->fresh())->not->toBeNull()->and($event->fresh()->series_id)->toBeNull();
});

it('sends guests to sign in to create a series', function () {
    $this->postJson(route('tournaments.series.store'), ['name' => 'X', 'points' => [3], 'participation_points' => 1])->assertUnauthorized();
    $this->get(route('tournaments.series.create'))->assertRedirect();
});
