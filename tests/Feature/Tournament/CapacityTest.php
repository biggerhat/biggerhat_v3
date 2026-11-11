<?php

use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRsvp;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'max_players' => 2]);
});

it('stores a player limit and a registration deadline from settings', function () {
    $closes = now()->addDays(3)->startOfHour();

    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), [
        'max_players' => 8, 'registration_closes_at' => $closes->toDateTimeString(),
    ])->assertOk();

    expect($this->tournament->fresh())->max_players->toBe(8)->and($this->tournament->fresh()->registration_closes_at->equalTo($closes))->toBeTrue();
});

it('validates the limit and refuses one below the current registration', function () {
    $url = route('tournaments.update', $this->tournament);
    $this->actingAs($this->owner)->putJson($url, ['max_players' => 1])->assertStatus(422)->assertJsonValidationErrors(['max_players']);
    $this->actingAs($this->owner)->putJson($url, ['max_players' => 'lots'])->assertStatus(422);

    TournamentPlayer::factory()->for($this->tournament)->count(2)->create();
    $this->tournament->update(['max_players' => 4]);
    $this->actingAs($this->owner)->putJson($url, ['max_players' => 2])->assertOk();
    TournamentPlayer::factory()->for($this->tournament)->create();
    $this->actingAs($this->owner)->putJson($url, ['max_players' => 2])->assertStatus(422);

    $this->actingAs($this->owner)->putJson($url, ['max_players' => null])->assertOk();
    expect($this->tournament->fresh()->max_players)->toBeNull();
});

it('lets the organizer add players up to the limit, but not past it (a ringer does not count)', function () {
    $add = fn (array $body) => $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->tournament), $body);

    $add(['display_name' => 'One', 'faction' => 'guild'])->assertOk();
    $add(['display_name' => 'Two', 'faction' => 'guild'])->assertOk();
    $add(['display_name' => 'Three', 'faction' => 'guild'])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'full (2)'));
    $add(['display_name' => 'Ringer', 'is_ringer' => true, 'faction' => 'guild'])->assertOk();

    expect($this->tournament->players()->count())->toBe(3);
});

it('refuses new RSVPs after the deadline but still lets people cancel', function () {
    $user = User::factory()->create();
    $this->tournament->update(['registration_closes_at' => now()->subMinute()]);

    $this->actingAs($user)->post(route('tournaments.rsvp', $this->tournament))->assertSessionHasErrors('rsvp');
    expect($this->tournament->rsvps()->count())->toBe(0);

    TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $user->id]);
    $this->actingAs($user)->delete(route('tournaments.rsvp.cancel', $this->tournament))->assertRedirect();
    expect($this->tournament->rsvps()->count())->toBe(0);

    $this->tournament->update(['registration_closes_at' => now()->addHour()]);
    $this->actingAs($user)->post(route('tournaments.rsvp', $this->tournament))->assertSessionHasNoErrors();
    expect($this->tournament->rsvps()->count())->toBe(1);
});

it('waitlists RSVPs past the limit in the order they arrived, and moves people up as seats free', function () {
    [$registered, $b, $c, $d] = User::factory()->count(4)->create()->all();
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $registered->id]);
    foreach ([$registered, $b, $c, $d] as $i => $u) {
        TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $u->id, 'created_at' => now()->addMinutes($i)]);
    }

    $positions = fn () => $this->tournament->fresh()->rsvps()->orderBy('id')->get()
        ->mapWithKeys(fn ($r) => [$r->user_id => $this->tournament->fresh()->waitlistPositions()[$r->id] ?? null])->all();

    // limit 2, one seat taken by $registered -> $b gets the last seat, $c and $d wait
    expect($positions())->toBe([$registered->id => null, $b->id => null, $c->id => 1, $d->id => 2]);

    $this->tournament->rsvps()->where('user_id', $b->id)->delete();
    expect($this->tournament->fresh()->waitlistPositions())->toHaveCount(1);
    expect($positions())->toBe([$registered->id => null, $c->id => null, $d->id => 1]);
});

it('shows each RSVP its waitlist position on the public page and to the organizer', function () {
    [$a, $b] = User::factory()->count(2)->create()->all();
    TournamentPlayer::factory()->for($this->tournament)->count(2)->create();
    $ra = TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $a->id]);
    $rb = TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $b->id, 'created_at' => now()->addMinute()]);

    foreach ([route('tournaments.view', $this->tournament->uuid), route('tournaments.manage', $this->tournament->uuid)] as $url) {
        $this->actingAs($this->owner)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tournament.max_players', 2)
            ->where('tournament.registration_closed', false)
            ->where('tournament.rsvps.0.waitlist_position', 1)
            ->where('tournament.rsvps.1.waitlist_position', 2));
    }
});

it('does not waitlist in team events (an RSVP is a person, the limit counts teams)', function () {
    $this->tournament->update(['team_size' => 2]);
    TournamentPlayer::factory()->for($this->tournament)->count(2)->create();
    TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => User::factory()->create()->id]);

    expect($this->tournament->fresh()->waitlistPositions())->toBe([]);
});

it('copies the limit (not the deadline) when starting from a previous event', function () {
    $this->actingAs($this->owner)->get(route('tournaments.create', ['copy' => $this->tournament->uuid]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('template.max_players', 2));
});
