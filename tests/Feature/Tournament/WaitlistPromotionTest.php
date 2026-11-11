<?php

use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRsvp;
use App\Models\User;
use App\Notifications\Tournament\TournamentWaitlistPromoted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'max_players' => 2]);

    $this->seated = User::factory()->create();
    $this->seatedPlayer = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->seated->id]);
    $this->other = TournamentPlayer::factory()->for($this->tournament)->create();
    TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $this->seated->id]);

    $this->first = User::factory()->create();
    $this->second = User::factory()->create();
    TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $this->first->id, 'created_at' => now()->addMinute()]);
    TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $this->second->id, 'created_at' => now()->addMinutes(2)]);
});

it('lists waitlisted users in order', function () {
    expect($this->tournament->waitlistedUserIds())->toBe([$this->first->id, $this->second->id]);
});

it('tells the next person when a player withdraws', function () {
    $this->actingAs($this->seated)->post(route('tournaments.withdraw', $this->tournament))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->first, TournamentWaitlistPromoted::class);
    Notification::assertNotSentTo($this->second, TournamentWaitlistPromoted::class);
});

it('tells the next person when an organizer removes a player', function () {
    $this->actingAs($this->owner)->deleteJson(route('tournaments.players.remove', [$this->tournament, $this->other]))->assertOk();

    Notification::assertSentTo($this->first, TournamentWaitlistPromoted::class);
    Notification::assertNotSentTo($this->second, TournamentWaitlistPromoted::class);
});

it('tells everyone who fits when the limit is raised', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), ['max_players' => 4])->assertOk();

    Notification::assertSentTo($this->first, TournamentWaitlistPromoted::class);
    Notification::assertSentTo($this->second, TournamentWaitlistPromoted::class);
});

it('stays quiet when nobody moves up', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), ['name' => 'Renamed'])->assertOk();

    Notification::assertNothingSent();
});
