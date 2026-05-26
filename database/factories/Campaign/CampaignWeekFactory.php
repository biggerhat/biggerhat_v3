<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignWeek>
 */
class CampaignWeekFactory extends Factory
{
    protected $model = CampaignWeek::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'week_number' => 1,
            'starts_at' => null,
            'weekly_event_id' => null,
            'notes' => null,
        ];
    }
}
