<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Notifications\Tournament\TournamentRoundTimeWarning;
use Illuminate\Support\Facades\Notification;

/** A running round started $minutesAgo minutes ago, with one unfinished game between two linked players. */
function runningRound(int $minutesAgo, int $limit = 60): array
{
    $tournament = Tournament::factory()->active()->create(['round_time_limit' => $limit]);
    $round = TournamentRound::factory()->for($tournament)->create([
        'round_number' => 1,
        'status' => TournamentRoundStatusEnum::InProgress,
        'started_at' => now()->subMinutes($minutesAgo),
    ]);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $one = TournamentPlayer::factory()->for($tournament)->create(['user_id' => $a->id]);
    $two = TournamentPlayer::factory()->for($tournament)->create(['user_id' => $b->id]);
    TournamentGame::factory()->for($round, 'round')->create(['player_one_id' => $one->id, 'player_two_id' => $two->id]);

    return [$round, $a, $b];
}

it('warns players with an unfinished game once the last-turn window opens', function () {
    Notification::fake();
    [$round, $a, $b] = runningRound(minutesAgo: 50); // 10 minutes left of 60, window is 15

    $this->artisan('tournaments:round-time-warnings')->assertSuccessful();

    Notification::assertSentTo([$a, $b], TournamentRoundTimeWarning::class);
    expect($round->fresh()->last_turn_notified_at)->not->toBeNull();
});

it('sends the warning only once per round', function () {
    Notification::fake();
    runningRound(minutesAgo: 50);

    $this->artisan('tournaments:round-time-warnings');
    $this->artisan('tournaments:round-time-warnings');

    Notification::assertCount(2);
});

it('stays quiet before the window and after the clock has run out', function () {
    Notification::fake();
    runningRound(minutesAgo: 20);
    runningRound(minutesAgo: 75);

    $this->artisan('tournaments:round-time-warnings')->assertSuccessful();

    Notification::assertNothingSent();
});

it('skips players whose game already has a result', function () {
    Notification::fake();
    [$round, $a, $b] = runningRound(minutesAgo: 50);
    $round->games()->update(['result' => 'completed']);

    $this->artisan('tournaments:round-time-warnings');

    Notification::assertNothingSent();
});

it('can warn again after the round is reset and restarted', function () {
    Notification::fake();
    [$round] = runningRound(minutesAgo: 50);
    $this->artisan('tournaments:round-time-warnings');
    $round->update(['last_turn_notified_at' => null, 'started_at' => now()->subMinutes(50)]);

    $this->artisan('tournaments:round-time-warnings');

    Notification::assertCount(4);
});
