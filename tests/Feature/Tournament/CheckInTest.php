<?php

use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id]);
    $this->player = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->user->id]);
});

it('lets a linked player check themselves in and out', function () {
    $this->actingAs($this->user)->post(route('tournaments.check_in', $this->tournament))->assertRedirect()->assertSessionHasNoErrors();
    expect($this->player->fresh()->checked_in_at)->not->toBeNull();

    $this->actingAs($this->user)->delete(route('tournaments.check_in.cancel', $this->tournament))->assertRedirect();
    expect($this->player->fresh()->checked_in_at)->toBeNull();
});

it('keeps the original check-in time when checking in twice', function () {
    $this->actingAs($this->user)->post(route('tournaments.check_in', $this->tournament));
    $first = $this->player->fresh()->checked_in_at;
    $this->travel(5)->minutes();
    $this->actingAs($this->user)->post(route('tournaments.check_in', $this->tournament));

    expect($this->player->fresh()->checked_in_at->equalTo($first))->toBeTrue();
});

it('rejects check-in for non-players, disqualified players, and outside registration / active', function () {
    $this->actingAs(User::factory()->create())->post(route('tournaments.check_in', $this->tournament))->assertForbidden();

    $this->player->update(['is_disqualified' => true]);
    $this->actingAs($this->user)->post(route('tournaments.check_in', $this->tournament))->assertForbidden();

    $this->player->update(['is_disqualified' => false]);
    $this->tournament->update(['status' => 'completed']);
    $this->actingAs($this->user)->post(route('tournaments.check_in', $this->tournament))->assertSessionHasErrors('check_in');
});

it('lets the organizer check one player in or out through the player update', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$this->tournament, $this->player]), ['checked_in' => true])->assertOk();
    expect($this->player->fresh()->checked_in_at)->not->toBeNull();

    $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$this->tournament, $this->player]), ['checked_in' => false])->assertOk();
    expect($this->player->fresh()->checked_in_at)->toBeNull();
});

it('checks in everyone who has not arrived, skipping disqualified players', function () {
    $other = TournamentPlayer::factory()->for($this->tournament)->create();
    $dq = TournamentPlayer::factory()->for($this->tournament)->create(['is_disqualified' => true]);
    $already = TournamentPlayer::factory()->for($this->tournament)->create(['checked_in_at' => now()->subHour()]);

    $this->actingAs($this->owner)->postJson(route('tournaments.players.check_in_all', $this->tournament))
        ->assertOk()->assertJsonPath('checked_in', 2);

    expect($this->player->fresh()->checked_in_at)->not->toBeNull()->and($other->fresh()->checked_in_at)->not->toBeNull()
        ->and($dq->fresh()->checked_in_at)->toBeNull()
        ->and($already->fresh()->checked_in_at->lt(now()->subMinutes(30)))->toBeTrue();
});

it('only lets organizers check everyone in', function () {
    $this->actingAs($this->user)->postJson(route('tournaments.players.check_in_all', $this->tournament))->assertForbidden();
});

it('exposes check-in state on the public page', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$this->tournament, $this->player]), ['checked_in' => true]);

    $this->get(route('tournaments.view', $this->tournament))
        ->assertInertia(fn ($page) => $page->where('tournament.players.0.checked_in_at', fn ($v) => $v !== null));
});
