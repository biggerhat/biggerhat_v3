<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

/** Delivers one push message to one device. Provider implementations (FCM, later) live behind this. */
interface PushSender
{
    /**
     * @return bool false when the provider says the token is dead (uninstalled / expired) so the caller can drop it
     */
    public function send(DeviceToken $token, string $title, string $body, ?string $url): bool;
}
