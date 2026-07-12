<?php

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\Friend\FriendRequestReceived;

it('unread_notifications_count reflects real unread notifications', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $requester = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $user->id]);
    $user->notify(new FriendRequestReceived($friendship));

    $this->actingAs($user)
        ->get(route('friends.index'))
        ->assertInertia(fn ($page) => $page->where('unread_notifications_count', 1));
});

it('recent lists notifications for the header dropdown', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $requester = User::factory()->create(['name' => 'Alice']);
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $user->id]);
    $user->notify(new FriendRequestReceived($friendship));

    $this->actingAs($user)
        ->getJson(route('notifications.recent'))
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonFragment(['message' => 'Alice sent you a friend request.']);
});

it('index renders the full paginated notification history page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $requester = User::factory()->create(['name' => 'Alice']);
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $user->id]);
    $user->notify(new FriendRequestReceived($friendship));

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.message', 'Alice sent you a friend request.')
        );
});

it('markAsRead marks a single notification read', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $requester = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $user->id]);
    $user->notify(new FriendRequestReceived($friendship));
    $notificationId = $user->notifications()->first()->id;

    $this->actingAs($user)
        ->postJson(route('notifications.read', $notificationId))
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('markAllAsRead clears every unread notification', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $requesterA = User::factory()->create();
    $requesterB = User::factory()->create();
    $user->notify(new FriendRequestReceived(Friendship::factory()->create(['requester_id' => $requesterA->id, 'addressee_id' => $user->id])));
    $user->notify(new FriendRequestReceived(Friendship::factory()->create(['requester_id' => $requesterB->id, 'addressee_id' => $user->id])));

    $this->actingAs($user)
        ->postJson(route('notifications.read-all'))
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('a user cannot mark another user\'s notification as read', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $requester = User::factory()->create();
    $friendship = Friendship::factory()->create(['requester_id' => $requester->id, 'addressee_id' => $user->id]);
    $user->notify(new FriendRequestReceived($friendship));
    $notificationId = $user->notifications()->first()->id;

    $this->actingAs($stranger)
        ->postJson(route('notifications.read', $notificationId))
        ->assertOk();

    // No-op: the query is scoped to the acting user's own notifications.
    expect($user->unreadNotifications()->count())->toBe(1);
});
