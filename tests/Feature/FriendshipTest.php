<?php

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

function friendUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

it('sends a friend request', function () {
    Notification::fake();
    $requester = friendUser();
    $addressee = friendUser();

    $this->actingAs($requester)
        ->post(route('friends.store'), ['user_id' => $addressee->id])
        ->assertRedirect();

    $friendship = Friendship::where('requester_id', $requester->id)->where('addressee_id', $addressee->id)->first();
    expect($friendship)->not->toBeNull();
    expect($friendship->accepted_at)->toBeNull();

    Notification::assertSentTo($addressee, \App\Notifications\Friend\FriendRequestReceived::class);
});

it('rejects self-friending', function () {
    $user = friendUser();

    $this->actingAs($user)
        ->post(route('friends.store'), ['user_id' => $user->id])
        ->assertRedirect();

    expect(Friendship::count())->toBe(0);
});

it('rejects a duplicate request regardless of direction', function () {
    $a = friendUser();
    $b = friendUser();
    Friendship::factory()->create(['requester_id' => $a->id, 'addressee_id' => $b->id]);

    // b tries to friend a — same pair, opposite direction.
    $this->actingAs($b)
        ->post(route('friends.store'), ['user_id' => $a->id])
        ->assertRedirect();

    expect(Friendship::count())->toBe(1);
});

it('accepts a friend request and notifies the requester', function () {
    Notification::fake();
    $requester = friendUser();
    $addressee = friendUser();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $addressee->id]);

    $this->actingAs($addressee)
        ->post(route('friends.accept', $friendship))
        ->assertRedirect();

    expect($friendship->fresh()->accepted_at)->not->toBeNull();
    expect($requester->isFriendsWith($addressee))->toBeTrue();

    Notification::assertSentTo($requester, \App\Notifications\Friend\FriendRequestAccepted::class);
});

it('blocks a stranger from accepting a request meant for someone else', function () {
    $requester = friendUser();
    $addressee = friendUser();
    $stranger = friendUser();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $addressee->id]);

    $this->actingAs($stranger)
        ->post(route('friends.accept', $friendship))
        ->assertForbidden();
});

it('declining a pending request deletes the row', function () {
    $requester = friendUser();
    $addressee = friendUser();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $addressee->id]);

    $this->actingAs($addressee)
        ->delete(route('friends.destroy', $friendship))
        ->assertRedirect();

    expect(Friendship::find($friendship->id))->toBeNull();
});

it('unfriending an accepted friendship deletes the row', function () {
    $requester = friendUser();
    $addressee = friendUser();
    $friendship = Friendship::factory()->accepted()->create(['requester_id' => $requester->id, 'addressee_id' => $addressee->id]);

    $this->actingAs($requester)
        ->delete(route('friends.destroy', $friendship))
        ->assertRedirect();

    expect(Friendship::find($friendship->id))->toBeNull();
    expect($requester->isFriendsWith($addressee))->toBeFalse();
});

it('blocks an outsider from removing a friendship they are not party to', function () {
    $requester = friendUser();
    $addressee = friendUser();
    $stranger = friendUser();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $addressee->id]);

    $this->actingAs($stranger)
        ->delete(route('friends.destroy', $friendship))
        ->assertForbidden();
});

it('index lists friends and pending requests, sent and received, separately', function () {
    $user = friendUser();
    $friend = friendUser();
    $incomingRequester = friendUser();
    $outgoingAddressee = friendUser();

    Friendship::factory()->accepted()->create(['requester_id' => $user->id, 'addressee_id' => $friend->id]);
    Friendship::factory()->create(['requester_id' => $incomingRequester->id, 'addressee_id' => $user->id]);
    Friendship::factory()->create(['requester_id' => $user->id, 'addressee_id' => $outgoingAddressee->id]);

    $this->actingAs($user)
        ->get(route('friends.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Friends/Index')
            ->has('friends', 1)
            ->where('friends.0.user.id', $friend->id)
            ->has('requests_received', 1)
            ->where('requests_received.0.user.id', $incomingRequester->id)
            ->has('requests_sent', 1)
            ->where('requests_sent.0.user.id', $outgoingAddressee->id)
        );
});
