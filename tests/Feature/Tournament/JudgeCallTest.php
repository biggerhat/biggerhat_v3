<?php

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Notifications\Tournament\TournamentJudgeCalled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Cache::flush();
    $this->owner = User::factory()->create();
    $this->scorer = User::factory()->create();
    $this->me = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->tournament->scorers()->attach($this->scorer->id);
    $this->round = TournamentRound::factory()->for($this->tournament)->create(['round_number' => 1, 'status' => TournamentRoundStatusEnum::InProgress]);
    $a = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->me->id]);
    $b = TournamentPlayer::factory()->for($this->tournament)->create();
    $this->game = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $a->id, 'player_two_id' => $b->id, 'table_number' => 4]);
    $this->url = route('tournaments.games.judge', [$this->tournament, $this->game]);
});

it('tells the organizers and scorekeepers which table wants a judge', function () {
    Notification::fake();

    $this->actingAs($this->me)->post($this->url)->assertRedirect();

    Notification::assertSentTo([$this->owner, $this->scorer], TournamentJudgeCalled::class, fn ($n) => $n->table === 4 && $n->round === 1);
    Notification::assertNotSentTo($this->me, TournamentJudgeCalled::class);
});

it('allows one call per table every few minutes', function () {
    Notification::fake();

    $this->actingAs($this->me)->post($this->url)->assertSessionHasNoErrors();
    $this->actingAs($this->me)->post($this->url)->assertSessionHasErrors('judge');

    Notification::assertSentToTimes($this->owner, TournamentJudgeCalled::class, 1);
});

it('is only for players in the game, while it is being played', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create())->post($this->url)->assertForbidden();

    $this->round->update(['status' => TournamentRoundStatusEnum::Completed]);
    $this->actingAs($this->me)->post($this->url)->assertSessionHasErrors('judge');

    Notification::assertNothingSent();
});
