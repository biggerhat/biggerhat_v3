<?php

use App\Enums\PermissionEnum;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    $this->creator = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->creator->id]);
});

it('rejects adding a second ringer', function () {
    TournamentPlayer::factory()->for($this->tournament)->ringer()->create();

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.players.add', $this->tournament->uuid), [
            'display_name' => 'Second Ringer',
            'faction' => 'guild',
            'is_ringer' => true,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Tournament already has a ringer']);
});

it('rejects linking the same user to two players when adding', function () {
    $linkedUser = User::factory()->create();
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $linkedUser->id]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.players.add', $this->tournament->uuid), [
            'display_name' => 'Duplicate',
            'faction' => 'guild',
            'user_id' => $linkedUser->id,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'That user is already linked to another player in this tournament']);
});

it('rejects linking the same user to two players when updating', function () {
    $linkedUser = User::factory()->create();
    TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $linkedUser->id]);
    $other = TournamentPlayer::factory()->for($this->tournament)->create();

    $this->actingAs($this->creator)
        ->putJson(route('tournaments.players.update', [$this->tournament->uuid, $other]), [
            'user_id' => $linkedUser->id,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'That user is already linked to another player in this tournament']);
});

it('returns 404 when targeting a player from a different tournament (scoped binding)', function () {
    $otherT = Tournament::factory()->create();
    $foreignPlayer = TournamentPlayer::factory()->for($otherT)->create();

    $this->actingAs($this->creator)
        ->putJson(route('tournaments.players.update', [$this->tournament->uuid, $foreignPlayer]), [
            'display_name' => 'X',
        ])
        ->assertStatus(404);
});

it('rejects scoring a game before the round is started', function () {
    $this->tournament->update(['status' => \App\Enums\TournamentStatusEnum::Active]);
    $round = \App\Models\TournamentRound::factory()->for($this->tournament)->create();
    $p1 = TournamentPlayer::factory()->for($this->tournament)->create();
    $p2 = TournamentPlayer::factory()->for($this->tournament)->create();
    $game = \App\Models\TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
    ]);

    $this->actingAs($this->creator)
        ->putJson(route('tournaments.games.update', [$this->tournament->uuid, $game]), [
            'player_one_strategy_vp' => 3,
            'player_one_scheme_vp' => 2,
            'player_two_strategy_vp' => 1,
            'player_two_scheme_vp' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Start the round before entering scores']);
});

it('rejects forfeiting a game before the round is started', function () {
    $this->tournament->update(['status' => \App\Enums\TournamentStatusEnum::Active]);
    $round = \App\Models\TournamentRound::factory()->for($this->tournament)->create();
    $p1 = TournamentPlayer::factory()->for($this->tournament)->create();
    $p2 = TournamentPlayer::factory()->for($this->tournament)->create();
    $game = \App\Models\TournamentGame::factory()->for($round, 'round')->create([
        'player_one_id' => $p1->id, 'player_two_id' => $p2->id,
    ]);

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.games.forfeit', [$this->tournament->uuid, $game]), [
            'forfeit_player_id' => $p2->id,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Start the round before assigning forfeits']);
});

it('blocks a stranger from managing players', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson(route('tournaments.players.add', $this->tournament->uuid), [
            'display_name' => 'Hijack',
            'faction' => 'guild',
        ])
        ->assertStatus(403);
});
