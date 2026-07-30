<?php

namespace App\Http\Controllers\Campaign\Concerns;

use App\Models\Campaign\Campaign;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Mirrors Game\Concerns\BroadcastsGameEvents for Campaign Mode (T3-34). A solo
 * campaign has exactly one member — nothing to broadcast to — so the guard
 * mirrors that trait's is_solo check.
 */
trait BroadcastsCampaignEvents
{
    protected function broadcastToCampaign(Campaign $campaign, ShouldBroadcast $event): void
    {
        if (! $campaign->is_solo) {
            broadcast($event)->toOthers();
        }
    }
}
