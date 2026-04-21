<?php

use App\Enums\PermissionEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    $this->creator = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->creator->id]);
});

it('lets the TO add a round while the tournament is active', function () {
    TournamentRound::factory()->for($this->tournament)->completed()->create(['round_number' => 1]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.rounds.create', $this->tournament->uuid))
        ->assertOk();

    expect($this->tournament->fresh()->rounds()->count())->toBe(2);
});

it('lets the TO delete a setup round during an active tournament', function () {
    TournamentRound::factory()->for($this->tournament)->completed()->create(['round_number' => 1]);
    $r2 = TournamentRound::factory()->for($this->tournament)->create(['round_number' => 2]);

    $this->actingAs($this->creator)
        ->deleteJson(route('tournaments.rounds.delete', [$this->tournament->uuid, $r2]))
        ->assertOk();

    expect($this->tournament->fresh()->rounds()->where('round_number', 2)->exists())->toBeFalse();
});

it('refuses to delete a middle round while later rounds exist', function () {
    // Regression: deleting a middle round previously left subsequent rounds
    // unpairable because canPairRound hunts for round_number - 1. Blocking
    // middle-round deletion is the simplest safeguard.
    TournamentRound::factory()->for($this->tournament)->completed()->create(['round_number' => 1]);
    $r2 = TournamentRound::factory()->for($this->tournament)->completed()->create(['round_number' => 2]);
    TournamentRound::factory()->for($this->tournament)->create(['round_number' => 3]);

    $this->actingAs($this->creator)
        ->deleteJson(route('tournaments.rounds.delete', [$this->tournament->uuid, $r2]))
        ->assertStatus(422)
        ->assertJsonFragment([
            'error' => 'Delete the later rounds first — removing a middle round would strand the rounds that follow it.',
        ]);

    expect($this->tournament->fresh()->rounds()->where('round_number', 2)->exists())->toBeTrue();
});

it('refuses to delete a round that is currently in progress', function () {
    $r = TournamentRound::factory()->for($this->tournament)->inProgress()->create(['round_number' => 1]);

    $this->actingAs($this->creator)
        ->deleteJson(route('tournaments.rounds.delete', [$this->tournament->uuid, $r]))
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Cannot delete a round that is in progress.']);
});

it('only marks bye games Completed when the round is started, not at pair time', function () {
    // Set up a Round 1 with 3 players (forces a bye).
    $players = \App\Models\TournamentPlayer::factory()->for($this->tournament)->count(3)->create();
    $strategy = \App\Models\Strategy::factory()->create();
    $schemes = \App\Models\Scheme::factory()->count(3)->create();
    $round = TournamentRound::factory()->for($this->tournament)->create([
        'round_number' => 1,
        'strategy_id' => $strategy->id,
        'deployment' => 'standard',
        'scheme_pool' => $schemes->pluck('id')->all(),
    ]);

    // Auto-pair: the 3-player odd count → one bye game.
    $this->actingAs($this->creator)
        ->postJson(route('tournaments.rounds.pair', [$this->tournament->uuid, $round]))
        ->assertOk();

    $bye = $round->fresh()->games()->where('is_bye', true)->first();
    expect($bye)->not->toBeNull();
    expect($bye->result->value)->toBe('pending'); // NOT completed yet

    // Now Start the round — bye should auto-complete.
    $this->actingAs($this->creator)
        ->putJson(route('tournaments.rounds.update', [$this->tournament->uuid, $round]), [
            'status' => 'in_progress',
        ])
        ->assertOk();

    expect($bye->fresh()->result->value)->toBe('completed');
});

it('auto-pairs round 2 after round 1 completes', function () {
    // Regression: the service used to column-subset-load the players relation,
    // which then tripped strict-mode missing-attribute errors the moment the
    // standings pipeline read is_disqualified / dropped_after_round. Only
    // surfaced round 2+ because round 1 skips sortByStandings.
    $players = \App\Models\TournamentPlayer::factory()->for($this->tournament)->count(4)->create();
    [$a, $b, $c, $d] = $players->all();

    $r1 = TournamentRound::factory()->for($this->tournament)->completed()->create(['round_number' => 1]);
    \App\Models\TournamentGame::factory()->for($r1, 'round')->withScore(7, 4)->create([
        'player_one_id' => $a->id, 'player_two_id' => $b->id,
    ]);
    \App\Models\TournamentGame::factory()->for($r1, 'round')->withScore(6, 5)->create([
        'player_one_id' => $c->id, 'player_two_id' => $d->id,
    ]);

    $strategy = \App\Models\Strategy::factory()->create();
    $schemes = \App\Models\Scheme::factory()->count(3)->create();
    $r2 = TournamentRound::factory()->for($this->tournament)->create([
        'round_number' => 2,
        'strategy_id' => $strategy->id,
        'deployment' => 'standard',
        'scheme_pool' => $schemes->pluck('id')->all(),
    ]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.rounds.pair', [$this->tournament->uuid, $r2]))
        ->assertOk();

    expect($r2->fresh()->games()->count())->toBe(2);
});

it('lets the TO add a player between rounds during an active tournament', function () {
    TournamentRound::factory()->for($this->tournament)->completed()->create(['round_number' => 1]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.players.add', $this->tournament->uuid), [
            'display_name' => 'Late Joiner',
            'faction' => 'guild',
        ])
        ->assertOk();

    expect($this->tournament->fresh()->players()->where('display_name', 'Late Joiner')->exists())->toBeTrue();
});

it('refuses to add a player while a round is in progress', function () {
    TournamentRound::factory()->for($this->tournament)->inProgress()->create(['round_number' => 1]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.players.add', $this->tournament->uuid), [
            'display_name' => 'Mid-Round Joiner',
            'faction' => 'guild',
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Cannot add a player while a round is in progress — wait for the round to finish.']);
});

it('lets the TO undrop a player at any time', function () {
    $p = \App\Models\TournamentPlayer::factory()->for($this->tournament)->create(['dropped_after_round' => 1]);

    $this->actingAs($this->creator)
        ->putJson(route('tournaments.players.update', [$this->tournament->uuid, $p]), [
            'dropped_after_round' => null,
        ])
        ->assertOk();

    expect($p->fresh()->dropped_after_round)->toBeNull();
});

it('refuses to delete a player who has played games', function () {
    $a = \App\Models\TournamentPlayer::factory()->for($this->tournament)->create();
    $b = \App\Models\TournamentPlayer::factory()->for($this->tournament)->create();
    $r = TournamentRound::factory()->for($this->tournament)->completed()->create();
    \App\Models\TournamentGame::factory()->for($r, 'round')->withScore(7, 4)->create([
        'player_one_id' => $a->id, 'player_two_id' => $b->id,
    ]);

    $this->actingAs($this->creator)
        ->deleteJson(route('tournaments.players.remove', [$this->tournament->uuid, $a]))
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'This player has played games — drop them instead so their match history stays intact.']);
});

// ─── Tracker game creation timing ───

it('does not create tracker games when auto-pairing a Setup round', function () {
    $opponent = User::factory()->create();
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->creator->id]);
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $opponent->id]);

    // Scenario must be set before pairing (state machine guard).
    $strategy = \App\Models\Strategy::factory()->create();
    $schemes = \App\Models\Scheme::factory()->count(3)->create();
    $round = TournamentRound::factory()->for($this->tournament)->create([
        'round_number' => 1,
        'status' => TournamentRoundStatusEnum::Setup,
        'strategy_id' => $strategy->id,
        'deployment' => 'standard',
        'scheme_pool' => $schemes->pluck('id')->toArray(),
    ]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.rounds.pair', [$this->tournament->uuid, $round]))
        ->assertOk();

    // Pair created TournamentGame rows, but tracker Game rows shouldn't exist
    // yet — they get materialized when the round is Started.
    expect($round->fresh()->games()->whereNotNull('game_id')->count())->toBe(0);
    expect(Game::count())->toBe(0);
});

it('blocks auto-pair when the round scenario is not configured', function () {
    $opponent = User::factory()->create();
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->creator->id]);
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $opponent->id]);
    $round = TournamentRound::factory()->for($this->tournament)->create([
        'round_number' => 1,
        'status' => TournamentRoundStatusEnum::Setup,
        'strategy_id' => null,
        'deployment' => null,
        'scheme_pool' => null,
    ]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.rounds.pair', [$this->tournament->uuid, $round]))
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Set the round scenario before pairing (missing: strategy, deployment, scheme pool)']);

    expect($round->fresh()->games()->count())->toBe(0);
});

it('creates tracker games when the round transitions to InProgress', function () {
    $opponent = User::factory()->create();
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->creator->id]);
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $opponent->id]);

    // State machine requires a fully configured scenario before the round can Start.
    $strategy = \App\Models\Strategy::factory()->create();
    $schemes = \App\Models\Scheme::factory()->count(3)->create();
    $round = TournamentRound::factory()->for($this->tournament)->create([
        'round_number' => 1,
        'status' => TournamentRoundStatusEnum::Setup,
        'strategy_id' => $strategy->id,
        'deployment' => 'standard',
        'scheme_pool' => $schemes->pluck('id')->toArray(),
    ]);

    // Pair first — no tracker games yet.
    $this->actingAs($this->creator)
        ->postJson(route('tournaments.rounds.pair', [$this->tournament->uuid, $round]))
        ->assertOk();
    expect(Game::count())->toBe(0);

    // Start the round — tracker games materialize now.
    $this->actingAs($this->creator)
        ->putJson(route('tournaments.rounds.update', [$this->tournament->uuid, $round]), [
            'status' => TournamentRoundStatusEnum::InProgress->value,
        ])
        ->assertOk();

    expect($round->fresh()->games()->whereNotNull('game_id')->count())->toBe(1);
    expect(Game::count())->toBe(1);
});
