<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile email can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('name cannot be changed via the profile form, even if submitted', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Hacked Name',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->fresh()->name)->toBe('Original Name');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('profile page exposes is_supporter and show_on_supporters_page for a supporter', function () {
    Role::firstOrCreate(['name' => RoleEnum::Supporter->value, 'guard_name' => 'web']);
    $user = User::factory()->create(['show_on_supporters_page' => false]);
    $user->assignRole(RoleEnum::Supporter->value);

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('is_supporter', true)->where('show_on_supporters_page', false));
});

test('a supporter can opt in to the public supporters page', function () {
    Role::firstOrCreate(['name' => RoleEnum::Supporter->value, 'guard_name' => 'web']);
    $user = User::factory()->create(['show_on_supporters_page' => false]);
    $user->assignRole(RoleEnum::Supporter->value);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'email' => $user->email,
            'show_on_supporters_page' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->show_on_supporters_page)->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});
