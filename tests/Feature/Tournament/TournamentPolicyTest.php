<?php

use App\Enums\PermissionEnum;
use App\Models\Tournament;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::CreateTournaments->value, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    $this->organizer = Role::firstOrCreate(['name' => 'tournament_organizer', 'guard_name' => 'web'])
        ->givePermissionTo(PermissionEnum::CreateTournaments->value);
    $this->admin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
        ->givePermissionTo([PermissionEnum::CreateTournaments->value, PermissionEnum::ManageTournaments->value]);
});

it('lets the creator manage their tournament', function () {
    $creator = User::factory()->create()->assignRole('tournament_organizer');
    $t = Tournament::factory()->create(['creator_id' => $creator->id]);

    expect($creator->can('manage', $t))->toBeTrue();
    expect($creator->can('delete', $t))->toBeTrue();
    expect($creator->can('addOrganizer', $t))->toBeTrue();
});

it('lets an invited organizer manage but not delete or add organizers', function () {
    $creator = User::factory()->create();
    $invited = User::factory()->create();
    $t = Tournament::factory()->create(['creator_id' => $creator->id]);
    $t->organizers()->attach($invited->id);

    expect($invited->can('manage', $t))->toBeTrue();
    expect($invited->can('delete', $t))->toBeFalse();
    expect($invited->can('addOrganizer', $t))->toBeFalse();
});

it('lets a super_admin do anything via the policy before() hook', function () {
    $admin = User::factory()->create()->assignRole('super_admin');
    $other = User::factory()->create();
    $t = Tournament::factory()->create(['creator_id' => $other->id]);

    expect($admin->can('manage', $t))->toBeTrue();
    expect($admin->can('delete', $t))->toBeTrue();
    expect($admin->can('addOrganizer', $t))->toBeTrue();
});

it('blocks a random user from managing a tournament', function () {
    $creator = User::factory()->create();
    $stranger = User::factory()->create();
    $t = Tournament::factory()->create(['creator_id' => $creator->id]);

    expect($stranger->can('manage', $t))->toBeFalse();
    expect($stranger->can('delete', $t))->toBeFalse();
});

it('only lets users with create_tournaments create new tournaments', function () {
    $organizer = User::factory()->create()->assignRole('tournament_organizer');
    $stranger = User::factory()->create();

    expect($organizer->can('create', Tournament::class))->toBeTrue();
    expect($stranger->can('create', Tournament::class))->toBeFalse();
});
