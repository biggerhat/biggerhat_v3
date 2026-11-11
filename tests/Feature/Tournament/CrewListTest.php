<?php

use App\Enums\FactionEnum;
use App\Models\CrewBuild;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->owner->id, 'encounter_size' => 50]);
    $this->player = TournamentPlayer::factory()->for($this->tournament)->create(['user_id' => $this->user->id, 'faction' => null]);
    $this->build = CrewBuild::factory()->create(['user_id' => $this->user->id, 'faction' => FactionEnum::Guild, 'encounter_size' => 50]);
    $this->submit = fn (?int $id, ?User $as = null) => $this->actingAs($as ?? $this->user)->put(route('tournaments.crew_list', $this->tournament), ['crew_build_id' => $id]);
});

it('attaches one of the player\'s own crews and adopts its faction when none was declared', function () {
    ($this->submit)($this->build->id)->assertSessionHasNoErrors();

    expect($this->player->fresh())->crew_build_id->toBe($this->build->id)->and($this->player->fresh()->getRawOriginal('faction'))->toBe('guild');
});

it('clears the submission with null', function () {
    ($this->submit)($this->build->id);
    ($this->submit)(null)->assertSessionHasNoErrors();

    expect($this->player->fresh()->crew_build_id)->toBeNull();
});

it('rejects someone else\'s crew, an archived one, and a different faction than the one declared', function () {
    $theirs = CrewBuild::factory()->create(['faction' => FactionEnum::Guild]);
    ($this->submit)($theirs->id)->assertSessionHasErrors('crew');

    $archived = CrewBuild::factory()->archived()->create(['user_id' => $this->user->id, 'faction' => FactionEnum::Guild]);
    ($this->submit)($archived->id)->assertSessionHasErrors('crew');

    TournamentPlayer::whereKey($this->player->id)->update(['faction' => 'arcanists']);
    ($this->submit)($this->build->id)->assertSessionHasErrors('crew');
    expect($this->player->fresh()->crew_build_id)->toBeNull();
});

it('locks once the tournament starts, and is not for non-players, teams or Garrison events', function () {
    ($this->submit)($this->build->id, User::factory()->create())->assertForbidden();

    $this->tournament->update(['status' => 'active']);
    ($this->submit)($this->build->id)->assertSessionHasErrors('crew');

    $this->tournament->update(['status' => 'registration', 'team_size' => 2]);
    ($this->submit)($this->build->id)->assertSessionHasErrors('crew');

    $this->tournament->update(['team_size' => 1, 'ruleset' => 'tos_fields_of_glory']);
    ($this->submit)($this->build->id)->assertSessionHasErrors('crew');
    expect($this->player->fresh()->crew_build_id)->toBeNull();
});

it('shows the organizer each list with flags, and keeps it out of the public payload', function () {
    $small = CrewBuild::factory()->create(['user_id' => $this->user->id, 'faction' => FactionEnum::Guild, 'encounter_size' => 40]);
    ($this->submit)($small->id);

    $this->actingAs($this->owner)->get(route('tournaments.manage', $this->tournament->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tournament.players.0.crew.name', $small->name)
            ->where('tournament.players.0.crew.problems.0', fn ($p) => str_contains($p, 'Built for 40ss'))
            ->missing('tournament.players.0.crewBuild')
            ->missing('tournament.players.0.crew_build'));

    auth()->logout();
    $this->get(route('tournaments.view', $this->tournament->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('tournament.players.0.crew_build_id')->missing('tournament.players.0.crew')->where('crew_lists', null));
});

it('gives a registered player the picker data and nobody else', function () {
    $this->actingAs($this->user)->get(route('tournaments.view', $this->tournament->uuid))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('crew_lists.open', true)
            ->where('crew_lists.builds.0.id', $this->build->id)
            ->where('crew_lists.selected_id', null));

    $this->actingAs(User::factory()->create())->get(route('tournaments.view', $this->tournament->uuid))->assertInertia(fn (Assert $page) => $page->where('crew_lists', null));
});

it('lets the organizer open a private crew submitted to their event, but not a stranger', function () {
    ($this->submit)($this->build->id);

    $this->actingAs($this->owner)->get(route('tools.crew_builder.share', $this->build->share_code))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/CrewBuilder/View'));
    $this->actingAs(User::factory()->create())->get(route('tools.crew_builder.share', $this->build->share_code))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tools/CrewBuilder/Private'));
});

it('stores the require-lists setting and copies it to the next event', function () {
    $this->actingAs($this->owner)->putJson(route('tournaments.update', $this->tournament), ['require_crew_lists' => true])->assertOk();
    expect($this->tournament->fresh()->require_crew_lists)->toBeTrue();

    $this->actingAs($this->owner)->get(route('tournaments.create', ['copy' => $this->tournament->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('template.require_crew_lists', true));
});
