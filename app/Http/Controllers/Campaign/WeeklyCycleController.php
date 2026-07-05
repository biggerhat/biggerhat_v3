<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignWeek;
use App\Models\Campaign\WeeklyEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Organizer-driven advancement of a campaign's week counter. On each step:
 *
 *   1. current_week += 1 (no-op if already at length_weeks; UI nudges to End).
 *   2. Create a campaign_weeks row for the new week.
 *   3. If `weekly_event_active` is on, roll the WeeklyEvent table once
 *      (with "Bullet With Your Name on It" reflip semantics deferred to a
 *      later iteration — for now we honor is_one_time by reflipping when a
 *      previous week already rolled the same one-time event).
 *
 * Per-player mandatory hire enforcement happens in WeeklyHireController.
 */
class WeeklyCycleController extends Controller
{
    public function advance(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status !== CampaignStatusEnum::Active) {
            return redirect()->back()->withMessage(
                'Campaign must be active to advance the week.',
                null,
                MessageTypeEnum::error,
            );
        }

        if ($campaign->current_week >= $campaign->length_weeks) {
            return redirect()->back()->withMessage(
                'Campaign has reached its planned final week. End the campaign to retire arsenals.',
                null,
                MessageTypeEnum::error,
            );
        }

        $newWeek = null;
        $event = null;

        DB::transaction(function () use ($campaign, &$newWeek, &$event) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->first();

            // Re-check under the lock — the pre-lock checks above are only a
            // UX guard against a concurrent request that already advanced.
            if (! $locked || $locked->status !== CampaignStatusEnum::Active || $locked->current_week >= $locked->length_weeks) {
                return;
            }

            $newWeek = $locked->current_week + 1;
            $locked->update(['current_week' => $newWeek]);

            if ($locked->weekly_event_active) {
                $event = $this->rollWeeklyEvent($locked, $newWeek);
            }

            CampaignWeek::create([
                'campaign_id' => $locked->id,
                'week_number' => $newWeek,
                'starts_at' => now(),
                'weekly_event_id' => $event?->id,
            ]);
        });

        if ($newWeek === null) {
            return redirect()->back()->withMessage(
                'Campaign has already been advanced.',
                null,
                MessageTypeEnum::error,
            );
        }

        $msg = "Advanced to week {$newWeek}.";
        if ($event) {
            $msg .= " Weekly event: {$event->name}.";
        }

        return redirect()->route('campaigns.show', $campaign)->withMessage($msg);
    }

    private function rollWeeklyEvent(Campaign $campaign, int $newWeek): ?WeeklyEvent
    {
        // Exclude one-time events already rolled in earlier weeks.
        $previouslyRolledOneTime = CampaignWeek::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('weekly_event_id')
            ->whereHas('weeklyEvent', fn ($q) => $q->where('is_one_time', true))
            ->pluck('weekly_event_id');

        $event = WeeklyEvent::query()
            ->whereNotIn('id', $previouslyRolledOneTime)
            ->inRandomOrder()
            ->first();

        return $event;
    }
}
