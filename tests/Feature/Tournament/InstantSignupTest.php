<?php

use App\Models\Tournament;
use App\Models\User;
use App\Notifications\Tournament\TournamentWaitlistPromoted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'max_players' => 2, 'instant_signup' => true]);
    $this->signUp = fn (User $u, array $body = []) => $this->actingAs($u)->post(route('tournaments.rsvp', $this->tournament), $body);
});

it('turns a sign-up into a player straight away, with the declared faction', function () {
    $user = User::factory()->create(['name' => 'Ada Player']);

    ($this->signUp)($user, ['faction' => 'guild'])->assertRedirect();

    $player = $this->tournament->players()->where('user_id', $user->id)->first();
    expect($player)->not->toBeNull()
        ->and($player->display_name)->toBe('Ada Player')
        ->and($player->faction->value)->toBe('guild')
        ->and($this->tournament->rsvps()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('leaves the sign-up as an RSVP when the organizer has not turned the option on', function () {
    $this->tournament->update(['instant_signup' => false]);
    $user = User::factory()->create();

    ($this->signUp)($user);

    expect($this->tournament->players()->count())->toBe(0)->and($this->tournament->rsvps()->count())->toBe(1);
});

it('waitlists people past the limit, then seats them oldest first when a seat opens', function () {
    Notification::fake();
    [$a, $b, $c, $d] = User::factory()->count(4)->create()->all();
    foreach ([$a, $b, $c, $d] as $i => $u) {
        $this->travel($i + 1)->seconds();
        ($this->signUp)($u);
    }

    expect($this->tournament->players()->pluck('user_id')->all())->toEqualCanonicalizing([$a->id, $b->id]);

    $this->actingAs($a)->delete(route('tournaments.rsvp.cancel', $this->tournament))->assertRedirect();

    expect($this->tournament->players()->pluck('user_id')->all())->toEqualCanonicalizing([$b->id, $c->id]);
    Notification::assertSentTo($c, TournamentWaitlistPromoted::class);
    Notification::assertNotSentTo($d, TournamentWaitlistPromoted::class);
});

it('never seats more than the limit', function () {
    foreach (User::factory()->count(5)->create() as $u) {
        ($this->signUp)($u);
    }

    expect($this->tournament->players()->count())->toBe(2)->and($this->tournament->rsvps()->count())->toBe(5);
});

it('does not add a player while a round is in progress', function () {
    $this->tournament->rounds()->create(['round_number' => 1, 'status' => 'in_progress']);
    $user = User::factory()->create();
    ($this->signUp)($user);
    expect($this->tournament->players()->count())->toBe(0)->and($this->tournament->rsvps()->count())->toBe(1);
});

it('refuses to cancel once the player is in the pairings', function () {
    $user = User::factory()->create();
    ($this->signUp)($user);
    $player = $this->tournament->players()->first();
    $round = $this->tournament->rounds()->create(['round_number' => 1, 'status' => 'completed']);
    $other = \App\Models\TournamentPlayer::factory()->for($this->tournament)->create();
    \App\Models\TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $player->id, 'player_two_id' => $other->id]);

    $this->actingAs($user)->delete(route('tournaments.rsvp.cancel', $this->tournament))->assertSessionHasErrors('rsvp');

    expect($this->tournament->players()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('lets the organizer switch the option from settings', function () {
    $this->tournament->update(['instant_signup' => false]);
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), ['instant_signup' => true])->assertOk();
    expect($this->tournament->fresh()->instant_signup)->toBeTrue();
});

it('seats the people who already signed up when the organizer switches it on', function () {
    $this->tournament->update(['instant_signup' => false]);
    [$a, $b, $c] = User::factory()->count(3)->create()->all();
    foreach ([$a, $b, $c] as $i => $u) {
        $this->travel($i + 1)->seconds();
        ($this->signUp)($u);
    }
    expect($this->tournament->players()->count())->toBe(0);

    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), ['instant_signup' => true])->assertOk();

    expect($this->tournament->players()->pluck('user_id')->all())->toEqualCanonicalizing([$a->id, $b->id]);
});

it('is chosen when creating an event', function () {
    $this->actingAs($this->owner)->post(route('tournaments.store'), [
        'name' => 'Open night', 'encounter_size' => 50, 'planned_rounds' => 3, 'event_date' => now()->addWeek()->toDateString(),
        'season' => $this->tournament->season->value, 'instant_signup' => true,
    ])->assertRedirect();

    expect(Tournament::where('name', 'Open night')->first()?->instant_signup)->toBeTrue();
});
