<?php

namespace App\Events;

use App\Models\Campaign\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * current_week incremented (WeeklyCycleController::advance()) — changes what
 * Weekly Hire offers/requires for every crew, so members watching the hub or
 * their own Weekly Hire page get a targeted reload instead of a manual
 * refresh (T3-34).
 */
class CampaignWeekAdvanced implements ShouldBroadcastNow
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
        return 'CampaignWeekAdvanced';
    }

    public function broadcastWith(): array
    {
        return [
            'current_week' => $this->campaign->current_week,
        ];
    }
}
