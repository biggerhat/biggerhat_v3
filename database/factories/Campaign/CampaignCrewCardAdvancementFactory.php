<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignCrewCardAdvancement>
 */
class CampaignCrewCardAdvancementFactory extends Factory
{
    protected $model = CampaignCrewCardAdvancement::class;

    public function definition(): array
    {
        return [
            'campaign_crew_id' => CampaignCrew::factory(),
            'crew_card_effect_id' => CampaignCrewCard::factory(),
            'crew_card_effect_type' => CampaignCrewCard::class,
            'acquired_aftermath_id' => null,
        ];
    }
}
