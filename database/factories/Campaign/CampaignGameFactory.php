<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignGame>
 */
class CampaignGameFactory extends Factory
{
    protected $model = CampaignGame::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'week_number' => 1,
            'crew_a_id' => CampaignCrew::factory(),
            'crew_b_id' => CampaignCrew::factory(),
            'base_game_id' => null,
            'encounter_size' => 50,
            'cr_a' => 0,
            'cr_b' => 0,
            'ss_bonus_to_lower' => 0,
            'vp_a' => 0,
            'vp_b' => 0,
            'schemes_completed_a' => 0,
            'schemes_completed_b' => 0,
            'status' => 'setup',
        ];
    }
}
