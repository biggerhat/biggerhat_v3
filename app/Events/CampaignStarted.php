<?php

namespace App\Events;

use App\Models\Campaign\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * planning -> active transition (CampaignController::start()). Lets every
 * member's hub (Campaigns/Show.vue) flip out of the "waiting to start" state
 * without a manual refresh (T3-34).
 */
class CampaignStarted implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Campaign $campaign,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('campaign.'.$this->campaign->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CampaignStarted';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->campaign->status->value,
        ];
    }
}
