<?php

namespace App\Events;

use App\Models\Campaign\CampaignCrew;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A crew's own data changed (arsenal hire/remove, equipment, advancement,
 * Starting Anew) — mirrors Game Tracker's GameCrewMemberUpdated. Other
 * campaign members watching the hub (Campaigns/Show.vue) or a shared Arsenal
 * Sheet get a targeted reload instead of needing a manual refresh (T3-34).
 */
class CampaignCrewUpdated implements ShouldBroadcastNow
{
    use Concerns\LogsBroadcast;
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CampaignCrew $crew,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('campaign.'.$this->crew->campaign_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CampaignCrewUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'crew_id' => $this->crew->id,
        ];
    }
}
