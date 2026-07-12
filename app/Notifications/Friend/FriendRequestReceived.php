<?php

namespace App\Notifications\Friend;

use App\Models\Friendship;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the addressee when someone sends them a friend request. Delivered
 * via the database channel (drives the notification bell dropdown) and the
 * broadcast channel (live push) — no broadcastOn()/channel-auth needed, the
 * default private channel (App.Models.User.{id}) is already authorized in
 * routes/channels.php.
 */
class FriendRequestReceived extends Notification
{
    public function __construct(public Friendship $friendship) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->friendship->loadMissing('requester:id,name');

        return [
            'type' => 'friend_request_received',
            'message' => "{$this->friendship->requester->name} sent you a friend request.",
            'actor' => ['id' => $this->friendship->requester->id, 'name' => $this->friendship->requester->name],
            'action_url' => route('friends.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'friend.request.received';
    }
}
