<?php

use App\Enums\PermissionEnum;
use App\Models\Tournament;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::ManageTournaments->value, 'guard_name' => 'web']);

    $this->admin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
        ->givePermissionTo([PermissionEnum::ManageTournaments->value]);
});

it('lets the creator manage their tournament', function () {
    $creator = User::factory()->create();
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

it('lets any signed-in user create tournaments', function () {
    expect(User::factory()->create()->can('create', Tournament::class))->toBeTrue();
});

it('lets a regular user open the create form and store a tournament without any permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tournaments.create'))->assertOk();

    $this->actingAs($user)->post(route('tournaments.store'), [
        'name' => 'Open Door Open',
        'event_date' => now()->addWeek()->toDateString(),
        'encounter_size' => 50,
        'encounter_type' => 'traditional',
        'planned_rounds' => 3,
        'season' => \App\Enums\PoolSeasonEnum::defaultSeason()->value,
        'round_time_limit' => 135,
        'bye_tp' => 3,
        'bye_diff' => 4,
        'bye_vp' => 6,
        'tiebreaker_mode' => 'diff_vp',
    ])->assertRedirect();

    $t = Tournament::where('name', 'Open Door Open')->first();
    expect($t)->not->toBeNull()
        ->and($t->creator_id)->toBe($user->id)
        ->and($user->can('manage', $t))->toBeTrue();
});

it('keeps the create form behind login', function () {
    $this->get(route('tournaments.create'))->assertRedirect(route('login'));
});

it('keeps other people\'s draft tournaments out of the public list', function () {
    $creator = User::factory()->create();
    Tournament::factory()->create(['creator_id' => $creator->id, 'name' => 'Secret Draft', 'status' => 'draft']);
    Tournament::factory()->create(['creator_id' => $creator->id, 'name' => 'Open Reg', 'status' => 'registration']);

    $this->actingAs(User::factory()->create())
        ->get(route('tournaments.index'))
        ->assertInertia(fn ($page) => $page
            ->has('public_tournaments', 1)
            ->where('public_tournaments.0.name', 'Open Reg'));
});

it('removes the retired tournament_organizer role and create_tournaments permission', function () {
    $role = Role::create(['name' => 'tournament_organizer', 'guard_name' => 'web']);
    $perm = Permission::create(['name' => 'create_tournaments', 'guard_name' => 'web']);
    $role->givePermissionTo($perm);
    User::factory()->create()->assignRole($role);

    (require database_path('migrations/2026_10_30_120000_remove_tournament_organizer_role.php'))->up();

    expect(Role::where('name', 'tournament_organizer')->exists())->toBeFalse()
        ->and(Permission::where('name', 'create_tournaments')->exists())->toBeFalse();
});

it('caps unfinished tournaments per creator but not finished ones or super admins', function () {
    $payload = fn (string $name) => [
        'name' => $name,
        'event_date' => now()->addWeek()->toDateString(),
        'encounter_size' => 50,
        'planned_rounds' => 3,
        'season' => \App\Enums\PoolSeasonEnum::defaultSeason()->value,
    ];
    $user = User::factory()->create();
    Tournament::factory()->count(5)->create(['creator_id' => $user->id, 'status' => 'draft']);

    $this->actingAs($user)->post(route('tournaments.store'), $payload('One too many'))->assertSessionHasErrors('name');
    expect(Tournament::where('name', 'One too many')->exists())->toBeFalse();

    Tournament::where('creator_id', $user->id)->first()->update(['status' => 'completed']);
    $this->actingAs($user)->post(route('tournaments.store'), $payload('Fits now'))->assertSessionHasNoErrors();
    expect(Tournament::where('name', 'Fits now')->exists())->toBeTrue();

    $admin = User::factory()->create();
    \Spatie\Permission\Models\Role::findOrCreate('super_admin');
    $admin->assignRole('super_admin');
    Tournament::factory()->count(6)->create(['creator_id' => $admin->id, 'status' => 'draft']);
    $this->actingAs($admin)->post(route('tournaments.store'), $payload('Admin event'))->assertSessionHasNoErrors();
});
