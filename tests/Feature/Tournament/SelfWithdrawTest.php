<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\TournamentRsvp;
use App\Models\User;
use App\Notifications\Tournament\TournamentPlayerWithdrew;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->player = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->user->id]);
    $this->url = fn () => route('tournaments.withdraw', $this->tournament);
});

it('removes the seat and the RSVP before the event starts, so the waitlist moves up', function () {
    $this->tournament->update(['status' => 'registration', 'max_players' => 1]);
    $waiting = User::factory()->create();
    TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $this->user->id]);
    $waitingRsvp = TournamentRsvp::create(['tournament_id' => $this->tournament->id, 'user_id' => $waiting->id, 'created_at' => now()->addMinute()]);
    expect($this->tournament->fresh()->waitlistPositions())->toBe([$waitingRsvp->id => 1]);

    $this->actingAs($this->user)->post(($this->url)())->assertRedirect()->assertSessionHasNoErrors();

    expect(TournamentPlayer::find($this->player->id))->toBeNull()
        ->and($this->tournament->rsvps()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and($this->tournament->fresh()->waitlistPositions())->toBe([]);
});

it('drops an active player after the last finished round when nothing is in progress', function () {
    TournamentRound::factory()->for($this->tournament)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::Completed]);
    TournamentRound::factory()->for($this->tournament)->create(['round_number' => 2, 'status' => TournamentRoundStatusEnum::Setup]);

    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasNoErrors();

    expect($this->player->fresh()->dropped_after_round)->toBe(1);
});

it('drops a player who has not played yet before round 1', function () {
    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasNoErrors();

    expect($this->player->fresh()->dropped_after_round)->toBe(0);
});

it('drops a player in a running round after that round, and tells the organizers they are still paired', function () {
    Notification::fake();
    $round = TournamentRound::factory()->for($this->tournament)->inProgress()->create(['round_number' => 2]);
    $other = TournamentPlayer::factory()->for($this->tournament)->create();
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $this->player->id, 'player_two_id' => $other->id]);

    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasNoErrors();

    expect($this->player->fresh()->dropped_after_round)->toBe(2);
    Notification::assertSentTo($this->owner, TournamentPlayerWithdrew::class, fn ($n) => $n->stillPaired && $n->playerName === $this->player->display_name);
});

it('rejects strangers, repeats, finished events and team events', function () {
    $this->actingAs(User::factory()->create())->post(($this->url)())->assertForbidden();

    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasErrors('withdraw');

    TournamentPlayer::whereKey($this->player->id)->update(['dropped_after_round' => null]);
    $this->tournament->update(['status' => 'completed']);
    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasErrors('withdraw');

    $this->tournament->update(['status' => 'active', 'team_size' => 2]);
    expect($this->tournament->fresh()->isTeamEvent())->toBeTrue();
    $this->actingAs($this->user)->post(($this->url)())->assertSessionHasErrors('withdraw');
    expect($this->player->fresh()->dropped_after_round)->toBeNull();
});
