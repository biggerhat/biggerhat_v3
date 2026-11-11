<?php

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentReportStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use App\Services\TournamentStandingsService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'allow_self_report' => true]);
    $this->round = TournamentRound::factory()->for($this->tournament)->inProgress()->create();
    $this->pa = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->alice->id]);
    $this->pb = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->bob->id]);
    $this->game = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $this->pa->id, 'player_two_id' => $this->pb->id]);
    $this->score = ['player_one_score' => ['strategy' => 4, 'scheme' => 3], 'player_two_score' => ['strategy' => 1, 'scheme' => 2]];
    $this->url = fn (string $name) => route("tournaments.games.{$name}", [$this->tournament, $this->game]);
});

it('lets a player submit a result that waits for the opponent and does not count yet', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score)->assertRedirect()->assertSessionHasNoErrors();

    $game = $this->game->fresh();
    expect($game->report_status)->toBe(TournamentReportStatusEnum::Pending)
        ->and($game->reported_by_player_id)->toBe($this->pa->id)
        ->and($game->reported_score['player_one'])->toBe(['strategy' => 4, 'scheme' => 3])
        ->and($game->result)->toBe(TournamentGameResultEnum::Pending);

    $row = collect(app(TournamentStandingsService::class)->compute($this->tournament))->firstWhere('player_id', $this->pa->id);
    expect($row['total_tp'])->toBe(0)->and($row['rounds_played'])->toBe(0);
});

it('makes the result official when the opponent confirms', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);
    $this->actingAs($this->bob)->post(($this->url)('report.confirm'))->assertRedirect()->assertSessionHasNoErrors();

    $game = $this->game->fresh();
    expect($game)->result->toBe(TournamentGameResultEnum::Completed)->player_one_vp->toBe(7)->player_two_vp->toBe(3)
        ->report_status->toBeNull()->reported_score->toBeNull();

    $row = collect(app(TournamentStandingsService::class)->compute($this->tournament))->firstWhere('player_id', $this->pa->id);
    expect($row['total_tp'])->toBe(3);
});

it('carries the agreed flag through confirmation', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), [...$this->score, 'agreed' => true]);
    $this->actingAs($this->bob)->post(($this->url)('report.confirm'));

    expect($this->game->fresh()->result)->toBe(TournamentGameResultEnum::Agreed);
});

it('does not let the reporter confirm their own result', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);

    $this->actingAs($this->alice)->post(($this->url)('report.confirm'))->assertSessionHasErrors('report');
    expect($this->game->fresh()->result)->toBe(TournamentGameResultEnum::Pending);
});

it('lets the opponent dispute with a note and leaves it for the organizer', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);
    $this->actingAs($this->bob)->post(($this->url)('report.dispute'), ['note' => 'I scored a scheme'])->assertSessionHasNoErrors();

    $game = $this->game->fresh();
    expect($game->report_status)->toBe(TournamentReportStatusEnum::Disputed)->and($game->report_note)->toBe('I scored a scheme')
        ->and($game->result)->toBe(TournamentGameResultEnum::Pending);
});

it('lets the organizer settle a disputed report by entering the score, which clears the report', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);
    $this->actingAs($this->bob)->post(($this->url)('report.dispute'), ['note' => 'no']);

    $this->actingAs($this->owner)->putJson(route('tournaments.games.update', [$this->tournament, $this->game]), [
        'player_one_score' => ['strategy' => 2, 'scheme' => 2], 'player_two_score' => ['strategy' => 3, 'scheme' => 3],
    ])->assertOk();

    $game = $this->game->fresh();
    expect($game)->player_one_vp->toBe(4)->player_two_vp->toBe(6)->report_status->toBeNull()->report_note->toBeNull();
});

it('lets the reporter withdraw, and either player start over after a dispute', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);
    $this->actingAs($this->alice)->delete(($this->url)('report.withdraw'))->assertSessionHasNoErrors();
    expect($this->game->fresh()->report_status)->toBeNull();

    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);
    $this->actingAs($this->bob)->post(($this->url)('report.dispute'));
    $this->actingAs($this->bob)->post(($this->url)('report'), $this->score)->assertSessionHasNoErrors();
    expect($this->game->fresh())->report_status->toBe(TournamentReportStatusEnum::Pending)->reported_by_player_id->toBe($this->pb->id);
});

it('stops the opponent from over-writing an open proposal with their own', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);

    $this->actingAs($this->bob)->post(($this->url)('report'), $this->score)->assertSessionHasErrors('report');
    expect($this->game->fresh()->reported_by_player_id)->toBe($this->pa->id);
});

it('rejects reporting when self-reporting is off, for strangers, and outside a running round', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->post(($this->url)('report'), $this->score)->assertForbidden();

    $this->tournament->update(['allow_self_report' => false]);
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score)->assertSessionHasErrors('report');

    $this->tournament->update(['allow_self_report' => true]);
    $this->round->update(['status' => 'completed']);
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score)->assertSessionHasErrors('report');
    expect($this->game->fresh()->report_status)->toBeNull();
});

it('rejects reporting a game that already has a result, and invalid scores', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), ['player_one_score' => ['strategy' => 9, 'scheme' => 3], 'player_two_score' => ['strategy' => 1]])
        ->assertSessionHasErrors(['player_one_score.strategy', 'player_two_score.scheme']);

    $this->game->update(['result' => TournamentGameResultEnum::Completed]);
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score)->assertSessionHasErrors('report');
});

it('refuses a stale confirmation once the organizer has already scored the game', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);
    $this->actingAs($this->owner)->putJson(route('tournaments.games.update', [$this->tournament, $this->game]), [
        'player_one_score' => ['strategy' => 0, 'scheme' => 0], 'player_two_score' => ['strategy' => 5, 'scheme' => 5],
    ])->assertOk();

    $this->actingAs($this->bob)->post(($this->url)('report.confirm'))->assertSessionHasErrors('report');
    expect($this->game->fresh())->player_one_vp->toBe(0)->player_two_vp->toBe(10);
});

it('drops a pending report when the game is forfeited', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);

    $this->actingAs($this->owner)->postJson(route('tournaments.games.forfeit', [$this->tournament, $this->game]), ['forfeit_player_id' => $this->pb->id])->assertOk();

    expect($this->game->fresh())->report_status->toBeNull()->is_forfeit->toBeTrue();
});

it('lets the organizer turn self-reporting on and off from settings', function () {
    $t = Tournament::factory()->create(['creator_id' => $this->owner->id]);
    expect($t->fresh()->allow_self_report)->toBeFalse();

    $this->actingAs($this->owner)->putJson(route('tournaments.update', $t), ['allow_self_report' => true])->assertOk();
    expect($t->fresh()->allow_self_report)->toBeTrue();
});

it('creates an event with self-reporting enabled and carries it when copying', function () {
    $this->actingAs($this->owner)->post(route('tournaments.store'), [
        'name' => 'Self Reporting Cup', 'encounter_size' => 50, 'planned_rounds' => 3,
        'season' => Tournament::factory()->make()->season->value, 'event_date' => now()->addWeek()->toDateString(),
        'allow_self_report' => true,
    ])->assertRedirect();

    $t = Tournament::latest('id')->first();
    expect($t->allow_self_report)->toBeTrue();

    $this->actingAs($this->owner)->get(route('tournaments.create', ['copy' => $t->uuid]))
        ->assertInertia(fn ($page) => $page->where('template.allow_self_report', true));
});

it('shows a pending report to the public page so the opponent’s card can offer confirm / dispute', function () {
    $this->actingAs($this->alice)->post(($this->url)('report'), $this->score);

    $this->actingAs($this->bob)->get(route('tournaments.view', $this->tournament))
        ->assertInertia(fn ($page) => $page
            ->where('tournament.allow_self_report', true)
            ->where('rounds.0.games.0.report_status', 'pending')
            ->where('rounds.0.games.0.reported_by_player_id', $this->pa->id)
            ->where('rounds.0.games.0.reported_score.player_one.strategy', 4));
});

it('refuses a score save that races End Round: the locked re-check sees the round already completed', function () {
    // The early status check passes (round in progress); by the time the save takes the round lock, End Round has won.
    $reads = 0;
    TournamentRound::retrieved(function (TournamentRound $round) use (&$reads) {
        if (++$reads === 2) {
            $round->status = \App\Enums\TournamentRoundStatusEnum::Completed;
        }
    });

    $this->actingAs($this->owner)->putJson(route('tournaments.games.update', [$this->tournament, $this->game]), [
        'player_one_score' => ['strategy' => 2, 'scheme' => 2], 'player_two_score' => ['strategy' => 3, 'scheme' => 3],
    ])->assertStatus(422)->assertJsonFragment(['error' => 'Scores are locked after the round is completed']);

    expect($this->game->fresh())->result->toBe(TournamentGameResultEnum::Pending)->player_one_vp->toBeNull();
});
