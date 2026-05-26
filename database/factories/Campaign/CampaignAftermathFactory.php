<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignAftermath>
 */
class CampaignAftermathFactory extends Factory
{
    protected $model = CampaignAftermath::class;

    public function definition(): array
    {
        return [
            'campaign_game_id' => CampaignGame::factory(),
            'campaign_crew_id' => CampaignCrew::factory(),
            'current_phase' => 1,
            'hand_drawn' => null,
            'hand_used' => null,
            'scrip_earned' => 0,
            'status' => 'open',
        ];
    }
}
