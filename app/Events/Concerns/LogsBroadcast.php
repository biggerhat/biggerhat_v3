<?php

namespace App\Events\Concerns;

use Illuminate\Support\Facades\Log;

trait LogsBroadcast
{
    public function broadcastWhen(): bool
    {
        $channels = collect($this->broadcastOn())->map(fn ($ch) => get_class($ch).':'.$ch->name)->implode(', ');
        $eventName = class_basename($this);
        $data = json_encode($this->broadcastWith());

        Log::channel('single')->info("[Broadcast] {$eventName} → [{$channels}] data={$data}");

        return true;
    }
}
