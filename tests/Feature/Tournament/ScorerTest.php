<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Notifications\Tournament\TournamentScorerAdded;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->scorer = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->tournament->scorers()->attach($this->scorer->id);
    $this->round = TournamentRound::factory()->for($this->tournament)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::InProgress]);
    $a = TournamentPlayer::factory()->for($this->tournament)->create();
    $b = TournamentPlayer::factory()->for($this->tournament)->create();
    $this->game = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 1]);
    $this->score = fn () => ['player_one_score' => ['strategy' => 3, 'scheme' => 2], 'player_two_score' => ['strategy' => 1, 'scheme' => 1]];
});

it('lets a scorekeeper open the scoring desk and enter a result', function () {
    $this->actingAs($this->scorer)->get(route('tournaments.score', $this->tournament))
        ->assertInertia(fn (Assert $page) => $page->component('Tournaments/Score')->where('tournament.id', $this->tournament->id)->has('ruleset')->has('standings'));

    $this->actingAs($this->scorer)->putJson(route('tournaments.games.update', [$this->tournament, $this->game]), ($this->score)())->assertOk();

    expect($this->game->fresh()->player_one_vp)->toBe(5)->and($this->game->fresh()->player_two_vp)->toBe(2);
});

it('lets a scorekeeper record a forfeit', function () {
    $this->actingAs($this->scorer)->postJson(route('tournaments.games.forfeit', [$this->tournament, $this->game]), ['forfeit_player_id' => $this->game->player_two_id])->assertStatus(200);
});

it('keeps a scorekeeper out of everything else', function () {
    $this->actingAs($this->scorer)->get(route('tournaments.manage', $this->tournament))->assertForbidden();
    $this->actingAs($this->scorer)->putJson(route('tournaments.update', $this->tournament), ['name' => 'Mine'])->assertForbidden();
    $this->actingAs($this->scorer)->postJson(route('tournaments.rounds.create', $this->tournament))->assertForbidden();
    $this->actingAs($this->scorer)->postJson(route('tournaments.scorers.add', $this->tournament), ['user_id' => $this->stranger->id])->assertForbidden();
    $this->actingAs($this->scorer)->putJson(route('tournaments.details', $this->tournament), ['details' => null])->assertForbidden();
});

it('lets the organizer open the scoring desk too, and turns strangers away', function () {
    $this->actingAs($this->owner)->get(route('tournaments.score', $this->tournament))->assertOk();
    $this->actingAs($this->stranger)->get(route('tournaments.score', $this->tournament))->assertForbidden();
    $this->actingAs($this->stranger)->putJson(route('tournaments.games.update', [$this->tournament, $this->game]), ($this->score)())->assertForbidden();
});

it('sends guests to sign in', function () {
    $this->get(route('tournaments.score', $this->tournament))->assertRedirect();
});

it('adds a scorekeeper, notifies them and refuses duplicates and organizers', function () {
    Notification::fake();
    $url = route('tournaments.scorers.add', $this->tournament);

    $this->actingAs($this->owner)->postJson($url, ['user_id' => $this->stranger->id])->assertOk();
    expect($this->tournament->isScorer($this->stranger->id))->toBeTrue();
    Notification::assertSentTo($this->stranger, TournamentScorerAdded::class);

    $this->actingAs($this->owner)->postJson($url, ['user_id' => $this->stranger->id])->assertStatus(422);
    $this->actingAs($this->owner)->postJson($url, ['user_id' => $this->owner->id])->assertStatus(422);
    $this->actingAs($this->owner)->postJson($url, ['user_id' => 999999])->assertJsonValidationErrors('user_id');
});

it('removes a scorekeeper', function () {
    $this->actingAs($this->owner)->deleteJson(route('tournaments.scorers.remove', [$this->tournament, $this->scorer->id]))->assertOk();

    expect($this->tournament->isScorer($this->scorer->id))->toBeFalse();
    $this->actingAs($this->scorer)->get(route('tournaments.score', $this->tournament))->assertForbidden();
    $this->actingAs($this->stranger)->deleteJson(route('tournaments.scorers.remove', [$this->tournament, $this->scorer->id]))->assertForbidden();
});

it('tells the public page whether to show the scoring desk link', function () {
    $this->actingAs($this->scorer)->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('can_score', true));
    $this->actingAs($this->stranger)->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('can_score', false));
    $this->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('can_score', false));
});

it('lists scorekeepers to organizers on the manage page', function () {
    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('tournament.scorers.0.id', $this->scorer->id));
});
