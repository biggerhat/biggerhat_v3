<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a signed-in user can register a device token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('device_tokens.store'), ['platform' => 'ios', 'token' => 'abc123'])
        ->assertOk()->assertJson(['ok' => true]);

    expect(DeviceToken::where('token', 'abc123')->first())
        ->user_id->toBe($user->id)
        ->platform->toBe('ios');
});

test('registering the same token again keeps one row and moves it to the new owner', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)->postJson(route('device_tokens.store'), ['platform' => 'android', 'token' => 'shared'])->assertOk();
    $this->actingAs($second)->postJson(route('device_tokens.store'), ['platform' => 'android', 'token' => 'shared'])->assertOk();

    expect(DeviceToken::where('token', 'shared')->count())->toBe(1)
        ->and(DeviceToken::where('token', 'shared')->value('user_id'))->toBe($second->id);
});

test('registration rejects an unknown platform and a missing token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('device_tokens.store'), ['platform' => 'windows', 'token' => 'x'])->assertJsonValidationErrors('platform');
    $this->actingAs($user)->postJson(route('device_tokens.store'), ['platform' => 'ios'])->assertJsonValidationErrors('token');
    expect(DeviceToken::count())->toBe(0);
});

test('guests cannot register or drop tokens', function () {
    $this->postJson(route('device_tokens.store'), ['platform' => 'ios', 'token' => 'x'])->assertUnauthorized();
    $this->deleteJson(route('device_tokens.destroy'), ['platform' => 'ios', 'token' => 'x'])->assertUnauthorized();
});

test('a user can drop their own token but not someone else\'s', function () {
    $user = User::factory()->create();
    $mine = DeviceToken::factory()->ios()->create(['user_id' => $user->id, 'token' => 'mine']);
    $theirs = DeviceToken::factory()->android()->create(['token' => 'theirs']);

    $this->actingAs($user)->deleteJson(route('device_tokens.destroy'), ['platform' => 'ios', 'token' => 'theirs'])->assertOk();
    expect($theirs->fresh())->not->toBeNull();

    $this->actingAs($user)->deleteJson(route('device_tokens.destroy'), ['platform' => 'ios', 'token' => 'mine'])->assertOk();
    expect($mine->fresh())->toBeNull();
});

test('deleting a user removes their device tokens', function () {
    $user = User::factory()->create();
    DeviceToken::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(DeviceToken::count())->toBe(0);
});
