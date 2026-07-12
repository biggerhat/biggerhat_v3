<?php

use App\Models\User;

it('finds users by name prefix', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    User::factory()->create(['name' => 'Alice Anderson']);
    User::factory()->create(['name' => 'Alicia Byrne']);
    User::factory()->create(['name' => 'Bob Carter']);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali')
        ->assertOk()
        ->assertJsonCount(2, 'users');
});

it('excludes the searching user from their own results', function () {
    $me = User::factory()->create(['name' => 'Alice Anderson', 'email_verified_at' => now()]);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=Ali')
        ->assertOk()
        ->assertJsonCount(0, 'users');
});

it('returns nothing for a query shorter than 2 characters', function () {
    $me = User::factory()->create(['email_verified_at' => now()]);
    User::factory()->create(['name' => 'Alice Anderson']);

    $this->actingAs($me)
        ->getJson(route('users.search').'?q=A')
        ->assertOk()
        ->assertJsonCount(0, 'users');
});

it('requires authentication', function () {
    $this->getJson(route('users.search').'?q=Alice')
        ->assertUnauthorized();
});
