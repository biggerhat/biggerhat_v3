<?php

use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    // Shared fixtures: a two-player tournament with a linked tracker game in_progress.
    $this->strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $this->schemes = Scheme::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $schemeIds = $this->schemes->pluck('id')->toArray();

    $this->creator = User::factory()->create();
    $this->opponent = User::factory()->create();

    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->creator->id]);
    $this->tp1 = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->creator->id]);
    $this->tp2 = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->opponent->id]);
    $this->round = TournamentRound::factory()->for($this->tournament)->create([
        'status' => TournamentRoundStatusEnum::InProgress,
        'strategy_id' => $this->strategy->id,
        'scheme_pool' => $schemeIds,
    ]);

    $this->trackerGame = Game::factory()->inProgress()->create([
        'creator_id' => $this->creator->id,
        'strategy_id' => $this->strategy->id,
        'scheme_pool' => $schemeIds,
        'is_solo' => false,
    ]);
    $this->gp1 = GamePlayer::factory()->create([
        'game_id' => $this->trackerGame->id,
        'user_id' => $this->creator->id,
        'slot' => 1,
        'current_scheme_id' => $this->schemes[0]->id,
        'scheme_pool' => $schemeIds,
    ]);
    $this->gp2 = GamePlayer::factory()->create([
        'game_id' => $this->trackerGame->id,
        'user_id' => $this->opponent->id,
        'slot' => 2,
        'current_scheme_id' => $this->schemes[1]->id,
        'scheme_pool' => $schemeIds,
    ]);

    $this->tgame = TournamentGame::factory()->for($this->round, 'round')->create([
        'player_one_id' => $this->tp1->id,
        'player_two_id' => $this->tp2->id,
        'game_id' => $this->trackerGame->id,
        'result' => TournamentGameResultEnum::Pending,
    ]);
});

// ─── Mid-game turn submission flows VP into linked TournamentGame ───

it('syncs live VP to the linked tournament game on each turn submit (duel)', function () {
    $this->actingAs($this->creator)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 2,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->tgame->refresh();
    expect($this->tgame->player_one_strategy_vp)->toBe(1);
    expect($this->tgame->player_one_scheme_vp)->toBe(2);
    expect($this->tgame->player_one_vp)->toBe(3);
    // Untouched slot 2 stays at zero until that player submits.
    expect($this->tgame->player_two_strategy_vp)->toBe(0);
    // Result stays Pending — only the TO confirms.
    expect($this->tgame->result)->toBe(TournamentGameResultEnum::Pending);
});

it('accumulates VP across multiple turns', function () {
    $this->actingAs($this->creator)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 1,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->actingAs($this->opponent)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 2,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    // Both submitted turn 1 — turn advances. Now submit turn 2.
    $this->actingAs($this->creator)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 1,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->tgame->refresh();
    expect($this->tgame->player_one_strategy_vp)->toBe(2);
    expect($this->tgame->player_one_scheme_vp)->toBe(2);
    expect($this->tgame->player_one_vp)->toBe(4);
    expect($this->tgame->player_two_strategy_vp)->toBe(1);
    expect($this->tgame->player_two_scheme_vp)->toBe(2);
    expect($this->tgame->player_two_vp)->toBe(3);
});

// ─── Solo games sync the same way ───

it('syncs VP for a solo tracker game where slot 2 has no user', function () {
    // Re-configure the tracker as solo (slot 2 is the "Opponent" non-user).
    $this->trackerGame->update(['is_solo' => true]);
    $this->gp2->update(['user_id' => null, 'opponent_name' => 'Jane Doe']);
    $this->tp2->update(['user_id' => null]);

    $this->actingAs($this->creator)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 2,
        'scheme_points' => 1,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->tgame->refresh();
    expect($this->tgame->player_one_strategy_vp)->toBe(2);
    expect($this->tgame->player_one_scheme_vp)->toBe(1);
    expect($this->tgame->player_one_vp)->toBe(3);
});

// Regression: tournament playerOne is the unlinked side, the user is tournament
// playerTwo. The factory now puts the BiggerHat user in tracker slot 1 (so the
// solo "you vs opponent" UX works), which means tracker slot 1 maps to
// tournament player_two_vp — sync-back must respect that user_id-based mapping
// instead of the old positional one.
it('syncs solo VP into player_two when the user is the tournament P2', function () {
    // Tournament P1 = unlinked (Jane Doe), Tournament P2 = the user (creator).
    $this->tp1->update(['user_id' => null, 'display_name' => 'Jane Doe']);
    $this->tp2->update(['user_id' => $this->creator->id]);

    // Tracker game built by the factory: user lives in slot 1 even though
    // they're tournament player_two_id.
    $this->trackerGame->update(['is_solo' => true]);
    $this->gp1->update(['user_id' => $this->creator->id]);
    $this->gp2->update(['user_id' => null, 'opponent_name' => 'Jane Doe']);

    $this->actingAs($this->creator)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 2,
        'scheme_points' => 1,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->tgame->refresh();
    // The user's points landed in tournament player_two_vp (because they're TP2),
    // not player_one_vp — even though tracker-side they were slot 1.
    expect($this->tgame->player_two_strategy_vp)->toBe(2);
    expect($this->tgame->player_two_scheme_vp)->toBe(1);
    expect($this->tgame->player_two_vp)->toBe(3);
    // Untouched tournament P1 (the unlinked Jane) stays at zero.
    expect($this->tgame->player_one_strategy_vp)->toBe(0);
});

// ─── Completed / forfeited tournament games are never overwritten ───

it('does not overwrite a tournament game whose TO has already confirmed', function () {
    $this->tgame->update([
        'result' => TournamentGameResultEnum::Completed,
        'player_one_vp' => 5,
        'player_one_strategy_vp' => 2,
        'player_one_scheme_vp' => 3,
    ]);

    $this->actingAs($this->creator)->postJson(route('games.play.turns.store', $this->trackerGame->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 1,
        'scheme_action' => 'scored',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->tgame->refresh();
    expect($this->tgame->player_one_vp)->toBe(5);
    expect($this->tgame->player_one_strategy_vp)->toBe(2);
});

// ─── TO confirm flow: warning when tracker still InProgress ───

it('blocks TO confirm with a warning while the tracker is InProgress', function () {
    $this->actingAs($this->creator)
        ->putJson(route('tournaments.games.update', [$this->tournament->uuid, $this->tgame->id]), [
            'player_one_strategy_vp' => 2,
            'player_one_scheme_vp' => 3,
            'player_two_strategy_vp' => 1,
            'player_two_scheme_vp' => 2,
        ])
        ->assertStatus(422)
        ->assertJson(['error' => 'tracker_in_progress']);

    $this->tgame->refresh();
    expect($this->tgame->result)->toBe(TournamentGameResultEnum::Pending);
});

it('allows TO confirm when override flag is passed even with InProgress tracker', function () {
    $this->actingAs($this->creator)
        ->putJson(route('tournaments.games.update', [$this->tournament->uuid, $this->tgame->id]), [
            'player_one_strategy_vp' => 2,
            'player_one_scheme_vp' => 3,
            'player_two_strategy_vp' => 1,
            'player_two_scheme_vp' => 2,
            'confirm_override' => true,
        ])
        ->assertOk();

    $this->tgame->refresh();
    expect($this->tgame->result)->toBe(TournamentGameResultEnum::Completed);
    expect($this->tgame->player_one_vp)->toBe(5);
    expect($this->tgame->player_two_vp)->toBe(3);
});

it('lets TO confirm without override once the tracker is Completed', function () {
    $this->trackerGame->update(['status' => GameStatusEnum::Completed]);

    $this->actingAs($this->creator)
        ->putJson(route('tournaments.games.update', [$this->tournament->uuid, $this->tgame->id]), [
            'player_one_strategy_vp' => 2,
            'player_one_scheme_vp' => 3,
            'player_two_strategy_vp' => 1,
            'player_two_scheme_vp' => 2,
        ])
        ->assertOk();

    $this->tgame->refresh();
    expect($this->tgame->result)->toBe(TournamentGameResultEnum::Completed);
});
