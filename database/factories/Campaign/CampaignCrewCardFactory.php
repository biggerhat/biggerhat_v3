<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignCrewCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignCrewCard>
 */
class CampaignCrewCardFactory extends Factory
{
    protected $model = CampaignCrewCard::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
        ];
    }
}
