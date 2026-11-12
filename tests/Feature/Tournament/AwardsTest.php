<?php

use App\Models\Tournament;
use App\Models\TournamentAward;
use App\Models\TournamentPlayer;
use App\Models\TournamentPlayerMember;
use App\Models\User;
use App\Notifications\Tournament\TournamentAwardGiven;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->create();
    $this->winner = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->player = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->winner->id]);
});

it('creates an award, assigns it and tells the recipient', function () {
    $res = $this->actingAs($this->owner)->postJson(route('tournaments.awards.store', $this->tournament), [
        'name' => 'Best Painted', 'player_id' => $this->player->id, 'note' => 'The Ten Thunders crew',
    ])->assertOk();

    expect($res->json('award.name'))->toBe('Best Painted');
    Notification::assertSentTo($this->winner, TournamentAwardGiven::class, fn ($n) => $n->awardName === 'Best Painted');
});

it('tells every member of a winning team', function () {
    $tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'team_size' => 2]);
    $team = TournamentPlayer::factory()->for($tournament)->create();
    $second = User::factory()->create();
    TournamentPlayerMember::factory()->create(['tournament_player_id' => $team->id, 'user_id' => $this->winner->id]);
    TournamentPlayerMember::factory()->create(['tournament_player_id' => $team->id, 'user_id' => $second->id]);

    $this->actingAs($this->owner)->postJson(route('tournaments.awards.store', $tournament), ['name' => 'Sportsmanship', 'player_id' => $team->id])->assertOk();

    Notification::assertSentTo([$this->winner, $second], TournamentAwardGiven::class);
});

it('keeps an unassigned award away from the public and does not notify', function () {
    $this->actingAs($this->owner)->postJson(route('tournaments.awards.store', $this->tournament), ['name' => 'Best Sport'])->assertOk();

    Notification::assertNothingSent();
    $this->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('awards', []));
    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament))->assertInertia(fn (Assert $page) => $page->has('awards', 1)->where('awards.0.player_id', null));
});

it('shows assigned awards on the public page with the recipient', function () {
    TournamentAward::create(['tournament_id' => $this->tournament->id, 'name' => 'Best Painted', 'player_id' => $this->player->id]);

    $this->get(route('tournaments.view', $this->tournament))->assertInertia(fn (Assert $page) => $page->where('awards.0.name', 'Best Painted')->where('awards.0.player_name', $this->player->display_name));
});

it('notifies only when the recipient changes', function () {
    $award = TournamentAward::create(['tournament_id' => $this->tournament->id, 'name' => 'Best Painted', 'player_id' => $this->player->id]);
    $other = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => User::factory()->create()->id]);
    $url = route('tournaments.awards.update', [$this->tournament, $award]);

    $this->actingAs($this->owner)->putJson($url, ['name' => 'Best Paint Job', 'player_id' => $this->player->id])->assertOk();
    Notification::assertNothingSent();

    $this->actingAs($this->owner)->putJson($url, ['name' => 'Best Paint Job', 'player_id' => $other->id])->assertOk();
    Notification::assertSentTo($other->user, TournamentAwardGiven::class);
    expect($award->fresh())->name->toBe('Best Paint Job')->player_id->toBe($other->id);
});

it('deletes an award', function () {
    $award = TournamentAward::create(['tournament_id' => $this->tournament->id, 'name' => 'x']);

    $this->actingAs($this->owner)->deleteJson(route('tournaments.awards.destroy', [$this->tournament, $award]))->assertOk();

    expect(TournamentAward::count())->toBe(0);
});

it('validates the name and keeps the recipient inside the event', function () {
    $url = route('tournaments.awards.store', $this->tournament);
    $foreign = TournamentPlayer::factory()->create();

    $this->actingAs($this->owner)->postJson($url, ['name' => ''])->assertJsonValidationErrors('name');
    $this->actingAs($this->owner)->postJson($url, ['name' => str_repeat('x', 81)])->assertJsonValidationErrors('name');
    $this->actingAs($this->owner)->postJson($url, ['name' => 'x', 'player_id' => $foreign->id])->assertJsonValidationErrors('player_id');
});

it('caps the number of awards', function () {
    TournamentAward::insert(collect(range(1, 12))->map(fn ($i) => ['tournament_id' => $this->tournament->id, 'name' => "A$i", 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()])->all());

    $this->actingAs($this->owner)->postJson(route('tournaments.awards.store', $this->tournament), ['name' => 'One more'])->assertStatus(422);
});

it('only lets organizers manage awards', function () {
    $award = TournamentAward::create(['tournament_id' => $this->tournament->id, 'name' => 'x']);
    $stranger = User::factory()->create();

    $this->postJson(route('tournaments.awards.store', $this->tournament), ['name' => 'x'])->assertUnauthorized();
    $this->actingAs($stranger)->postJson(route('tournaments.awards.store', $this->tournament), ['name' => 'x'])->assertForbidden();
    $this->actingAs($stranger)->putJson(route('tournaments.awards.update', [$this->tournament, $award]), ['name' => 'y'])->assertForbidden();
    $this->actingAs($stranger)->deleteJson(route('tournaments.awards.destroy', [$this->tournament, $award]))->assertForbidden();
});

it('does not reach an award of another event', function () {
    $other = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $award = TournamentAward::create(['tournament_id' => $other->id, 'name' => 'x']);

    $this->actingAs($this->owner)->deleteJson(route('tournaments.awards.destroy', [$this->tournament, $award]))->assertNotFound();
});

it('lists awards on the results PDF of a finished event', function () {
    $this->tournament->update(['status' => 'completed']);
    TournamentAward::create(['tournament_id' => $this->tournament->id, 'name' => 'Best Painted', 'player_id' => $this->player->id]);

    $this->get(route('tournaments.results_pdf', $this->tournament))->assertOk();
});
