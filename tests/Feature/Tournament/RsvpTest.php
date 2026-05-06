<?php

use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentRsvp;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    $this->creator = User::factory()->create();
    $this->user = User::factory()->create();
    $this->tournament = Tournament::factory()->create([
        'creator_id' => $this->creator->id,
        'status' => TournamentStatusEnum::Draft,
    ]);
});

it('stores an RSVP without a faction (faction is optional)', function () {
    $this->actingAs($this->user)
        ->post(route('tournaments.rsvp', $this->tournament->uuid))
        ->assertRedirect();

    $rsvp = TournamentRsvp::where('user_id', $this->user->id)->first();
    expect($rsvp)->not->toBeNull();
    expect($rsvp->faction)->toBeNull();
});

it('stores an RSVP with a chosen faction', function () {
    $this->actingAs($this->user)
        ->post(route('tournaments.rsvp', $this->tournament->uuid), [
            'faction' => FactionEnum::Arcanists->value,
        ])
        ->assertRedirect();

    $rsvp = TournamentRsvp::where('user_id', $this->user->id)->first();
    expect($rsvp)->not->toBeNull();
    expect($rsvp->faction)->toBe(FactionEnum::Arcanists);
});

it('rejects an RSVP with an invalid faction', function () {
    $this->actingAs($this->user)
        ->post(route('tournaments.rsvp', $this->tournament->uuid), [
            'faction' => 'not_a_real_faction',
        ])
        ->assertSessionHasErrors('faction');

    expect(TournamentRsvp::where('user_id', $this->user->id)->exists())->toBeFalse();
});
