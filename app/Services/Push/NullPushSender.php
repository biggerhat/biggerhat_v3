<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

/** Default until a push provider is configured (docs/plans/native-app.md): accepts everything, sends nothing. */
class NullPushSender implements PushSender
{
    public function send(DeviceToken $token, string $title, string $body, ?string $url): bool
    {
        return true;
    }
}
