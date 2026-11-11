<?php

use App\Enums\FactionEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Garrison;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentRsvp;
use App\Models\User;
use App\Services\TournamentStandingsService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->fog = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'ruleset' => 'tos_fields_of_glory']);
    $this->gg = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id]);
    $this->allegiance = Allegiance::factory()->create();
});

it('requires an Allegiance, not a faction, to add a Fields of Glory player', function () {
    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->fog), ['display_name' => 'Ann'])
        ->assertStatus(422)->assertJsonValidationErrors(['allegiance_id'])->assertJsonMissingValidationErrors(['faction']);

    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->fog), ['display_name' => 'Ann', 'allegiance_id' => $this->allegiance->id])
        ->assertOk();

    expect(TournamentPlayer::where('display_name', 'Ann')->first())->allegiance_id->toBe($this->allegiance->id)->faction->toBeNull();
});

it('still requires a faction for Gaining Grounds players', function () {
    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->gg), ['display_name' => 'Bob'])
        ->assertStatus(422)->assertJsonValidationErrors(['faction']);
});

it('links a publicly shared Garrison by share code and takes its Allegiance', function () {
    $garrison = Garrison::factory()->forAllegiance($this->allegiance)->create(['is_public' => true]);

    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->fog), [
        'display_name' => 'Cy', 'allegiance_id' => $this->allegiance->id, 'garrison_share_code' => $garrison->share_code,
    ])->assertOk();

    $player = TournamentPlayer::where('display_name', 'Cy')->first();
    expect($player->garrison_id)->toBe($garrison->id)->and($player->allegiance_id)->toBe($this->allegiance->id);
});

it('rejects a private Garrison that the linked user does not own, but accepts the owner’s own', function () {
    $stranger = User::factory()->create();
    $private = Garrison::factory()->forAllegiance($this->allegiance)->forUser($stranger)->create(['is_public' => false]);
    $payload = ['display_name' => 'Di', 'allegiance_id' => $this->allegiance->id, 'garrison_share_code' => $private->share_code];

    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->fog), $payload)
        ->assertStatus(422)->assertJsonFragment(['error' => 'That Garrison code was not found, or the Garrison is not shared publicly.']);

    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->fog), [...$payload, 'user_id' => $stranger->id])->assertOk();
    expect(TournamentPlayer::where('display_name', 'Di')->first()->garrison_id)->toBe($private->id);
});

it('rejects a Garrison from a different Allegiance than the one declared', function () {
    $garrison = Garrison::factory()->create(['is_public' => true]);

    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->fog), [
        'display_name' => 'Ed', 'allegiance_id' => $this->allegiance->id, 'garrison_share_code' => $garrison->share_code,
    ])->assertStatus(422)->assertJsonFragment(['error' => 'That Garrison belongs to a different Allegiance than the player declared.']);
});

it('unlinks a Garrison with an empty share code and leaves the Allegiance', function () {
    $garrison = Garrison::factory()->forAllegiance($this->allegiance)->create(['is_public' => true]);
    $player = TournamentPlayer::factory()->for($this->fog)->create(['allegiance_id' => $this->allegiance->id, 'garrison_id' => $garrison->id]);

    $this->actingAs($this->owner)->putJson(route('tournaments.players.update', [$this->fog, $player]), ['garrison_share_code' => ''])->assertOk();

    expect($player->fresh())->garrison_id->toBeNull()->allegiance_id->toBe($this->allegiance->id);
});

it('stores an intended Allegiance on an RSVP', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tournaments.rsvp', $this->fog), ['allegiance_id' => $this->allegiance->id])->assertRedirect();

    expect(TournamentRsvp::where('user_id', $user->id)->first()->allegiance_id)->toBe($this->allegiance->id);
});

it('exposes Allegiance and Garrison on the public view, hiding private share codes', function () {
    $public = Garrison::factory()->forAllegiance($this->allegiance)->create(['is_public' => true]);
    $private = Garrison::factory()->forAllegiance($this->allegiance)->create(['is_public' => false]);
    TournamentPlayer::factory()->for($this->fog)->create(['display_name' => 'Pub', 'allegiance_id' => $this->allegiance->id, 'garrison_id' => $public->id]);
    TournamentPlayer::factory()->for($this->fog)->create(['display_name' => 'Priv', 'allegiance_id' => $this->allegiance->id, 'garrison_id' => $private->id]);

    $this->get(route('tournaments.view', $this->fog))->assertInertia(fn (Assert $page) => $page
        ->where('tournament.players.0.allegiance.name', $this->allegiance->name)
        ->where('tournament.players.0.garrison.share_code', $public->share_code)
        ->missing('tournament.players.1.garrison.share_code')
        ->where('tournament.players.1.garrison.name', $private->name));
});

it('includes the declared Allegiance in standings rows', function () {
    $player = TournamentPlayer::factory()->for($this->fog)->create(['allegiance_id' => $this->allegiance->id, 'faction' => null]);

    $row = collect(app(TournamentStandingsService::class)->compute($this->fog))->firstWhere('player_id', $player->id);

    expect($row['allegiance'])->toBe(['slug' => $this->allegiance->slug, 'name' => $this->allegiance->name]);
});

it('exports the Allegiance name in the CSV for Fields of Glory', function () {
    $round = \App\Models\TournamentRound::factory()->for($this->fog)->create();
    [$a, $b] = TournamentPlayer::factory()->for($this->fog)->count(2)->create(['allegiance_id' => $this->allegiance->id, 'faction' => null])->all();
    \App\Models\TournamentGame::factory()->for($round, 'round')->withScore(8, 4)->create(['player_one_id' => $a->id, 'player_two_id' => $b->id]);

    $csv = $this->actingAs($this->owner)->get(route('tournaments.export_csv', $this->fog))->streamedContent();

    expect($csv)->toContain($this->allegiance->name);
});

it('returns the new player (and notifies the linked user) without serializing a partial tournament', function () {
    $user = User::factory()->create();

    $this->actingAs($this->owner)->postJson(route('tournaments.players.add', $this->gg), [
        'display_name' => 'Linked', 'faction' => FactionEnum::Arcanists->value, 'user_id' => $user->id,
    ])->assertOk()->assertJsonPath('player.display_name', 'Linked');

    expect($user->notifications()->count())->toBe(1);
});
