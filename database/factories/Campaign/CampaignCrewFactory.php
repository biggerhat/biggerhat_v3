<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignCrew>
 */
class CampaignCrewFactory extends Factory
{
    protected $model = CampaignCrew::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->name."'s Crew",
            'share_code' => Str::random(12),
            'faction' => null,
            'keyword_1_id' => null,
            'keyword_2_id' => null,
            'crew_card_effect_id' => null,
            'scrip' => 0,
            'total_wins' => 0,
        ];
    }
}
