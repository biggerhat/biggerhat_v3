<?php

use App\Enums\PermissionEnum;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\Tournament\TournamentOrganizerAdded;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    $this->creator = User::factory()->create();
    $this->tournament = Tournament::factory()->inRegistration()->create(['creator_id' => $this->creator->id]);
});

it('notifies a user when granted organizer access', function () {
    Notification::fake();
    $grantee = User::factory()->create();

    $this->actingAs($this->creator)
        ->postJson(route('tournaments.organizers.add', $this->tournament->uuid), [
            'user_id' => $grantee->id,
        ])
        ->assertOk();

    Notification::assertSentTo($grantee, TournamentOrganizerAdded::class);
});
