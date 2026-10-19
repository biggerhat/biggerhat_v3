<?php

use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\CrewBuild;
use App\Models\Tournament;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/*
 * Rejection cases for the highest-risk FormRequests: values that used to fall
 * through to an enum cast and 500 now fail validation with a 422/redirect.
 */

beforeEach(function () {
    foreach ([PermissionEnum::CreateTournaments, PermissionEnum::ManageTournaments] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(PermissionEnum::CreateTournaments->value);
});

function validTournamentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Spring Open',
        'encounter_size' => 50,
        'planned_rounds' => 3,
        'season' => PoolSeasonEnum::defaultSeason()->value,
        'event_date' => now()->addWeek()->toDateString(),
    ], $overrides);
}

dataset('bad tournament fields', [
    'missing name' => [['name' => ''], 'name'],
    'tiny encounter size' => [['encounter_size' => 10], 'encounter_size'],
    'too many rounds' => [['planned_rounds' => 9], 'planned_rounds'],
    'unknown season' => [['season' => 'nope'], 'season'],
    'unknown encounter type' => [['encounter_type' => 'nope'], 'encounter_type'],
    'bad date' => [['event_date' => 'not-a-date'], 'event_date'],
    'bye tp too high' => [['bye_tp' => 99], 'bye_tp'],
    'unknown tiebreaker' => [['tiebreaker_mode' => 'nope'], 'tiebreaker_mode'],
]);

it('rejects a bad tournament create', function (array $override, string $field) {
    $this->actingAs($this->user)
        ->post(route('tournaments.store'), validTournamentPayload($override))
        ->assertSessionHasErrors($field);

    expect(Tournament::count())->toBe(0);
})->with('bad tournament fields');

it('accepts a valid tournament create', function () {
    $this->actingAs($this->user)
        ->post(route('tournaments.store'), validTournamentPayload())
        ->assertSessionHasNoErrors();

    expect(Tournament::count())->toBe(1);
});

it('rejects an unknown season on tournament update', function () {
    $tournament = Tournament::factory()->create(['creator_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->put(route('tournaments.update', $tournament->uuid), ['season' => 'nope'])
        ->assertSessionHasErrors('season');
});

it('rejects an unknown faction on crew builds', function () {
    $this->actingAs($this->user)
        ->post(route('tools.crew_builder.store'), ['name' => 'Crew', 'faction' => 'nope'])
        ->assertSessionHasErrors('faction');
    expect(CrewBuild::count())->toBe(0);

    $build = CrewBuild::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user)
        ->put(route('tools.crew_builder.update', $build), ['faction' => 'nope'])
        ->assertSessionHasErrors('faction');
});

it('rejects empty and malformed bulk collection payloads', function (string $routeName, array $payload, string $field) {
    $this->actingAs($this->user)
        ->postJson(route($routeName), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'status: no ids' => ['collection.update_status_bulk', ['miniature_ids' => []], 'miniature_ids'],
    'status: unknown miniature' => ['collection.update_status_bulk', ['miniature_ids' => [999999]], 'miniature_ids.0'],
    'status: non-integer id' => ['collection.update_status_bulk', ['miniature_ids' => ['abc']], 'miniature_ids.0'],
    'remove: no ids' => ['collection.remove_bulk', [], 'miniature_ids'],
]);

it('rejects guests on the same write endpoints', function () {
    $this->postJson(route('collection.remove_bulk'), ['miniature_ids' => [1]])->assertUnauthorized();
    $this->postJson(route('tools.crew_builder.store'), ['name' => 'x', 'faction' => 'guild'])->assertUnauthorized();
});
