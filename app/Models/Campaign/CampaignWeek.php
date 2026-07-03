<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\CampaignWeekFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per calendar week of a campaign. Stores the rolled Weekly Event
 * (if optional rule enabled) and per-week organizer notes.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $week_number
 * @property \Carbon\CarbonImmutable|null $starts_at
 * @property int|null $weekly_event_id
 * @property string|null $notes
 *
 * @mixin IdeHelperCampaignWeek
 */
class CampaignWeek extends Model
{
    /** @use HasFactory<CampaignWeekFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): CampaignWeekFactory
    {
        return CampaignWeekFactory::new();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function weeklyEvent(): BelongsTo
    {
        return $this->belongsTo(WeeklyEvent::class);
    }
}
