<?php

namespace App\Notifications\Friend;

use App\Models\Friendship;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent back to the original requester when the addressee accepts.
 */
class FriendRequestAccepted extends Notification
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
        $this->friendship->loadMissing('addressee:id,name');

        return [
            'type' => 'friend_request_accepted',
            'message' => "{$this->friendship->addressee->name} accepted your friend request.",
            'actor' => ['id' => $this->friendship->addressee->id, 'name' => $this->friendship->addressee->name],
            'action_url' => route('friends.index'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'friend.request.accepted';
    }
}
