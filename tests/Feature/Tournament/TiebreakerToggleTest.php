<?php

use App\Enums\PermissionEnum;
use App\Models\Tournament;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);
});

it('lets the TO flip tiebreaker_mode mid-tournament when settings are otherwise locked', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->active()->create([
        'creator_id' => $creator->id,
        'tiebreaker_mode' => 'diff_vp',
    ]);

    test()->actingAs($creator)
        ->putJson(route('tournaments.update', $tournament->uuid), ['tiebreaker_mode' => 'sos'])
        ->assertOk();

    expect($tournament->fresh()->tiebreaker_mode->value)->toBe('sos');
});

it('still rejects non-tiebreaker settings when the tournament is locked', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->active()->create([
        'creator_id' => $creator->id,
    ]);

    test()->actingAs($creator)
        ->putJson(route('tournaments.update', $tournament->uuid), ['name' => 'Renamed'])
        ->assertStatus(422)
        ->assertJsonFragment(['error' => 'Tournament settings are locked after starting']);
});

it('rejects an invalid tiebreaker_mode value', function () {
    $creator = User::factory()->create();
    $tournament = Tournament::factory()->create(['creator_id' => $creator->id]);

    test()->actingAs($creator)
        ->putJson(route('tournaments.update', $tournament->uuid), ['tiebreaker_mode' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tiebreaker_mode']);
});
