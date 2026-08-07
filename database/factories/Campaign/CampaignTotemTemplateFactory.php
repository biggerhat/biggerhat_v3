<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignTotemTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignTotemTemplate>
 */
class CampaignTotemTemplateFactory extends Factory
{
    protected $model = CampaignTotemTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'title' => null,
            'faction' => null,
            'station' => null,
            'cost' => null,
            'health' => 3,
            'defense' => 4,
            'defense_suit' => null,
            'willpower' => 5,
            'willpower_suit' => null,
            'speed' => 4,
            'size' => 1,
            'base' => '30',
            'notes' => null,
            'campaign_totem_flip_value' => null,
            'campaign_is_black_joker_totem' => false,
            'campaign_is_red_joker_totem' => false,
            'campaign_totem_special_replace' => false,
            'campaign_is_mini_master' => false,
        ];
    }
}
