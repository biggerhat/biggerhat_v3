<?php

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id]);
    $this->round = TournamentRound::factory()->for($this->tournament)->create(['round_number' => 2]);
    [$this->a, $this->b, $this->c, $this->d, $this->e] = TournamentPlayer::factory()->for($this->tournament)->count(5)->create(['faction' => 'guild'])->all();
    $this->g1 = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $this->a->id, 'player_two_id' => $this->b->id, 'table_number' => 1, 'is_manual' => false]);
    $this->g2 = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $this->c->id, 'player_two_id' => $this->d->id, 'table_number' => 2, 'is_manual' => false]);
    $this->swap = fn (array $body) => $this->actingAs($this->owner)->postJson(route('tournaments.games.swap', [$this->tournament, $this->round]), $body);
});

it('swaps two players between pairings and keeps the changes through Auto Pair', function () {
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_two', 'other_game_id' => $this->g2->id, 'other_slot' => 'player_one'])
        ->assertOk()->assertJsonPath('warnings', []);

    expect($this->g1->fresh())->player_one_id->toBe($this->a->id)->player_two_id->toBe($this->c->id)->is_manual->toBeTruthy()
        ->and($this->g2->fresh())->player_one_id->toBe($this->b->id)->player_two_id->toBe($this->d->id)->is_manual->toBeTruthy();
});

it('swaps the faction snapshots with the players', function () {
    $this->g1->update(['player_two_faction' => 'bayou']);
    $this->g2->update(['player_one_faction' => 'neverborn']);

    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_two', 'other_game_id' => $this->g2->id, 'other_slot' => 'player_one'])->assertOk();

    expect($this->g1->fresh()->player_two_faction)->toBe('neverborn')->and($this->g2->fresh()->player_one_faction)->toBe('bayou');
});

it('puts an unpaired player into a slot, leaving the replaced player unpaired', function () {
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one', 'player_id' => $this->e->id])->assertOk();

    expect($this->g1->fresh())->player_one_id->toBe($this->e->id)->player_two_id->toBe($this->b->id)->is_manual->toBeTruthy();
    expect(TournamentGame::where('tournament_round_id', $this->round->id)->where(fn ($q) => $q->where('player_one_id', $this->a->id)->orWhere('player_two_id', $this->a->id))->exists())->toBeFalse();
});

it('warns when the new pairing is a rematch', function () {
    $earlier = TournamentRound::factory()->for($this->tournament)->create(['round_number' => 1]);
    TournamentGame::factory()->for($earlier, 'round')->create(['player_one_id' => $this->a->id, 'player_two_id' => $this->c->id]);

    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_two', 'other_game_id' => $this->g2->id, 'other_slot' => 'player_one'])
        ->assertOk()->assertJsonPath('warnings.0', fn ($w) => str_contains($w, 'already played in Round 1'));
});

it('rejects invalid swaps', function () {
    // same pairing
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one', 'other_game_id' => $this->g1->id, 'other_slot' => 'player_two'])->assertStatus(422);
    // empty slot (a bye has no second player)
    $bye = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $this->e->id, 'player_two_id' => null, 'is_bye' => true]);
    ($this->swap)(['game_id' => $bye->id, 'slot' => 'player_two', 'other_game_id' => $this->g1->id, 'other_slot' => 'player_one'])->assertStatus(422);
    // a player who is already paired / not in this event / dropped
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one', 'player_id' => $this->c->id])->assertStatus(422);
    $outsider = TournamentPlayer::factory()->for(Tournament::factory()->active()->create())->create();
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one', 'player_id' => $outsider->id])->assertStatus(422);
    $this->e->update(['dropped_after_round' => 1]);
    $bye->delete();
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one', 'player_id' => $this->e->id])->assertStatus(422);
    // a pairing from another round
    $other = TournamentGame::factory()->for(TournamentRound::factory()->for($this->tournament)->create(['round_number' => 3]), 'round')
        ->create(['player_one_id' => $this->a->id, 'player_two_id' => $this->b->id]);
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one', 'other_game_id' => $other->id, 'other_slot' => 'player_one'])->assertStatus(422);
    // missing target
    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_one'])->assertStatus(422);
});

it('refuses once the round has started', function () {
    $this->round->update(['status' => 'in_progress']);

    ($this->swap)(['game_id' => $this->g1->id, 'slot' => 'player_two', 'other_game_id' => $this->g2->id, 'other_slot' => 'player_one'])
        ->assertStatus(422)->assertJsonPath('error', 'Pairings can only be changed while the round is in setup');
    expect($this->g1->fresh()->player_two_id)->toBe($this->b->id);
});

it('is organizer-only', function () {
    $body = ['game_id' => $this->g1->id, 'slot' => 'player_two', 'other_game_id' => $this->g2->id, 'other_slot' => 'player_one'];
    $this->actingAs(User::factory()->create())->postJson(route('tournaments.games.swap', [$this->tournament, $this->round]), $body)->assertForbidden();
});

it('swaps table numbers between two pairings, organizer only, setup only', function () {
    $url = route('tournaments.games.swap_tables', [$this->tournament, $this->round]);
    $body = ['game_id' => $this->g1->id, 'other_game_id' => $this->g2->id];

    $this->actingAs(User::factory()->create())->postJson($url, $body)->assertForbidden();
    $this->actingAs($this->owner)->postJson($url, ['game_id' => $this->g1->id, 'other_game_id' => $this->g1->id])->assertStatus(422);

    $this->actingAs($this->owner)->postJson($url, $body)->assertOk();
    expect($this->g1->fresh())->table_number->toBe(2)->is_manual->toBeTruthy()->and($this->g2->fresh()->table_number)->toBe(1);

    $this->round->update(['status' => 'in_progress']);
    $this->actingAs($this->owner)->postJson($url, $body)->assertStatus(422);
    expect($this->g1->fresh()->table_number)->toBe(2);
});
