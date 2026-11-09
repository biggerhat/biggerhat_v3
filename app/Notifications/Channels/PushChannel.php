<?php

namespace App\Notifications\Channels;

use App\Services\Push\NullPushSender;
use App\Services\Push\PushSender;
use Illuminate\Notifications\Notification;

/**
 * Mobile push for the existing in-app notifications: reuses the `toArray()` payload (`message`, `action_url`) the
 * bell dropdown already renders, so adding 'push' to a notification's via() is all it takes. Does nothing until a
 * real PushSender is bound and the user has registered a device token.
 */
class PushChannel
{
    public function __construct(private PushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if ($this->sender instanceof NullPushSender || ! method_exists($notification, 'toArray')) {
            return;
        }

        if (! ($notifiable->push_notifications ?? true)) {
            return;
        }

        $payload = $notification->toArray($notifiable);
        $body = (string) ($payload['message'] ?? '');
        if ($body === '') {
            return;
        }

        foreach ($notifiable->deviceTokens()->get() as $token) {
            if (! $this->sender->send($token, config('app.name'), $body, $payload['action_url'] ?? null)) {
                $token->delete();
            }
        }
    }
}
