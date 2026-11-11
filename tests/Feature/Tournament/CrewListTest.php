<?php

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** A master whose display name is "{name}, {title}" (the observer builds it from the two). */
function masterNamed(string $name, ?string $title = null): Character
{
    return Character::factory()->state(['station' => CharacterStationEnum::Master])->create(['name' => $name, 'title' => $title]);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->mia = User::factory()->create();
    $this->rex = User::factory()->create();
    $this->tournament = Tournament::factory()->active()->create(['creator_id' => $this->owner->id, 'encounter_size' => 50]);
    $this->miaPlayer = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->mia->id, 'faction' => 'guild']);
    $this->rexPlayer = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->rex->id, 'faction' => 'bayou']);
    $this->round = TournamentRound::factory()->for($this->tournament)->inProgress()->create(['round_number' => 1]);
    $this->game = TournamentGame::factory()->for($this->round, 'round')->create(['player_one_id' => $this->rexPlayer->id, 'player_two_id' => $this->miaPlayer->id]);
    $this->guild = CrewBuild::factory()->create(['user_id' => $this->mia->id, 'faction' => FactionEnum::Guild, 'encounter_size' => 50,
        'master_id' => masterNamed('Lucius', 'Chief Magistrate')->id]);
    $this->attach = fn (?int $buildId, ?User $as = null, ?TournamentGame $game = null) => $this->actingAs($as ?? $this->mia)
        ->put(route('tournaments.games.crew', [$this->tournament, ($game ?? $this->game)->id]), ['crew_build_id' => $buildId]);
});

it('attaches the crew to the player\'s own side of the game and fills in the master and title', function () {
    ($this->attach)($this->guild->id)->assertSessionHasNoErrors();

    $game = $this->game->fresh();
    expect($game)->player_two_crew_build_id->toBe($this->guild->id)->player_one_crew_build_id->toBeNull()
        ->and($game->player_two_master)->toBe('Lucius')->and($game->player_two_title)->toBe('Lucius, Chief Magistrate')
        ->and($game->player_two_faction)->toBe('guild');
});

it('works from either side of the pairing', function () {
    $bayou = CrewBuild::factory()->create(['user_id' => $this->rex->id, 'faction' => FactionEnum::Bayou]);

    ($this->attach)($bayou->id, $this->rex)->assertSessionHasNoErrors();

    expect($this->game->fresh())->player_one_crew_build_id->toBe($bayou->id)->player_two_crew_build_id->toBeNull();
});

it('lets the crew differ from round to round — each pairing keeps its own', function () {
    $second = CrewBuild::factory()->create(['user_id' => $this->mia->id, 'faction' => FactionEnum::Guild, 'master_id' => masterNamed('Perdita', 'Ortega')->id]);
    $this->round->update(['status' => 'completed']);
    $this->game->update(['result' => 'completed']);
    $roundTwo = TournamentRound::factory()->for($this->tournament)->inProgress()->create(['round_number' => 2]);
    $gameTwo = TournamentGame::factory()->for($roundTwo, 'round')->create(['player_one_id' => $this->miaPlayer->id, 'player_two_id' => $this->rexPlayer->id]);

    // round 1's crew was attached before it was played; round 2 takes a different one
    TournamentGame::whereKey($this->game->id)->update(['player_two_crew_build_id' => $this->guild->id]);
    ($this->attach)($second->id, null, $gameTwo)->assertSessionHasNoErrors();

    expect($this->game->fresh()->player_two_crew_build_id)->toBe($this->guild->id)
        ->and($gameTwo->fresh())->player_one_crew_build_id->toBe($second->id)->player_one_title->toBe('Perdita, Ortega');
});

it('clears the crew with null', function () {
    ($this->attach)($this->guild->id);
    ($this->attach)(null)->assertSessionHasNoErrors();

    expect($this->game->fresh()->player_two_crew_build_id)->toBeNull();
});

it('rejects someone else\'s crew, an archived one, and a faction other than the one being played', function () {
    ($this->attach)(CrewBuild::factory()->create(['faction' => FactionEnum::Guild])->id)->assertSessionHasErrors('crew');
    ($this->attach)(CrewBuild::factory()->archived()->create(['user_id' => $this->mia->id, 'faction' => FactionEnum::Guild])->id)->assertSessionHasErrors('crew');
    ($this->attach)(CrewBuild::factory()->create(['user_id' => $this->mia->id, 'faction' => FactionEnum::Arcanists])->id)->assertSessionHasErrors('crew');

    expect($this->game->fresh()->player_two_crew_build_id)->toBeNull();
});

it('is for the two players of that game, in that tournament, before it has a result', function () {
    ($this->attach)($this->guild->id, User::factory()->create())->assertForbidden();

    $other = Tournament::factory()->active()->create();
    $this->actingAs($this->mia)->put(route('tournaments.games.crew', [$other, $this->game->id]), ['crew_build_id' => $this->guild->id])->assertNotFound();

    $this->game->update(['result' => 'completed']);
    ($this->attach)($this->guild->id)->assertSessionHasErrors('crew');
    $this->game->update(['result' => 'pending']);
    $this->round->update(['status' => 'completed']);
    ($this->attach)($this->guild->id)->assertSessionHasErrors('crew');
    expect($this->game->fresh()->player_two_crew_build_id)->toBeNull();
});

it('refuses byes, team events and Garrison-based events', function () {
    $bye = TournamentGame::factory()->for(TournamentRound::factory()->for($this->tournament)->create(['round_number' => 2]), 'round')
        ->create(['player_one_id' => $this->miaPlayer->id, 'player_two_id' => null, 'is_bye' => true]);
    ($this->attach)($this->guild->id, null, $bye)->assertSessionHasErrors('crew');

    $this->tournament->update(['team_size' => 2]);
    ($this->attach)($this->guild->id)->assertSessionHasErrors('crew');

    $this->tournament->update(['team_size' => 1, 'ruleset' => 'tos_fields_of_glory']);
    ($this->attach)($this->guild->id)->assertSessionHasErrors('crew');
    expect($this->game->fresh()->player_two_crew_build_id)->toBeNull();
});

it('enforces Alternating Leaders: a master can\'t be brought again in a later round', function () {
    $this->tournament->update(['variants' => ['alternating_leaders' => true]]);
    $this->game->update(['player_two_master' => 'Lucius', 'player_two_title' => 'Lucius, Chief Magistrate', 'result' => 'completed']);
    $this->round->update(['status' => 'completed']);
    $roundTwo = TournamentRound::factory()->for($this->tournament)->inProgress()->create(['round_number' => 2]);
    $gameTwo = TournamentGame::factory()->for($roundTwo, 'round')->create(['player_one_id' => $this->rexPlayer->id, 'player_two_id' => $this->miaPlayer->id]);

    ($this->attach)($this->guild->id, null, $gameTwo)->assertSessionHasErrors('crew');
    expect($gameTwo->fresh()->player_two_crew_build_id)->toBeNull();

    $fresh = CrewBuild::factory()->create(['user_id' => $this->mia->id, 'faction' => FactionEnum::Guild, 'master_id' => masterNamed('Sonnia', 'Criid')->id]);
    ($this->attach)($fresh->id, null, $gameTwo)->assertSessionHasNoErrors();
});

it('shows the organizer the crew on each side of every pairing with flags, and never the build itself', function () {
    $small = CrewBuild::factory()->create(['user_id' => $this->mia->id, 'faction' => FactionEnum::Guild, 'encounter_size' => 40]);
    ($this->attach)($small->id);

    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.rounds.0.games.0.crew_two.name', $small->name)
            ->where('tournament.rounds.0.games.0.crew_two.problems.0', fn ($p) => str_contains($p, 'Built for 40ss'))
            ->where('tournament.rounds.0.games.0.crew_one', null)
            ->missing('tournament.rounds.0.games.0.player_two_crew_build'));
});

it('keeps crews out of the public payload and gives a player the picker for their current game only', function () {
    ($this->attach)($this->guild->id);

    $this->actingAs($this->mia)->get(route('tournaments.view', $this->tournament->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('crew_lists.game_id', $this->game->id)->where('crew_lists.round_number', 1)->where('crew_lists.open', true)
            ->where('crew_lists.selected_id', $this->guild->id)->where('crew_lists.builds.0.id', $this->guild->id)
            ->missing('rounds.0.games.0.player_two_crew_build_id')->missing('rounds.0.games.0.crew_two'));

    $this->actingAs(User::factory()->create())->get(route('tournaments.view', $this->tournament->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('crew_lists', null));

    auth()->logout();
    $this->get(route('tournaments.view', $this->tournament->uuid))->assertInertia(fn (Assert $page) => $page->where('crew_lists', null));

    // once the game is scored the picker is closed, and with the round over there is nothing to pick for
    $this->game->update(['result' => 'completed']);
    $this->actingAs($this->mia)->get(route('tournaments.view', $this->tournament->uuid))->assertInertia(fn (Assert $page) => $page->where('crew_lists.open', false));
    $this->round->update(['status' => 'completed']);
    $this->actingAs($this->mia)->get(route('tournaments.view', $this->tournament->uuid))->assertInertia(fn (Assert $page) => $page->where('crew_lists', null));
});

it('lets the organizer open a private crew brought to their event, but not a stranger', function () {
    ($this->attach)($this->guild->id);

    $this->actingAs($this->owner)->get(route('tools.crew_builder.share', $this->guild->share_code))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/CrewBuilder/View'));
    $this->actingAs(User::factory()->create())->get(route('tools.crew_builder.share', $this->guild->share_code))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/CrewBuilder/Private'));
});

it('stores the require-crews setting and copies it to the next event', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), ['require_crew_lists' => true])->assertStatus(422); // active: settings locked
    $draft = Tournament::factory()->create(['creator_id' => $this->owner->id]);
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $draft), ['require_crew_lists' => true])->assertOk();
    expect($draft->fresh()->require_crew_lists)->toBeTrue();

    $this->actingAs($this->owner)->get(route('tournaments.create', ['copy' => $draft->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('template.require_crew_lists', true));
});
