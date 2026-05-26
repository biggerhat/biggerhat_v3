<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignArsenalModel>
 */
class CampaignArsenalModelFactory extends Factory
{
    protected $model = CampaignArsenalModel::class;

    public function definition(): array
    {
        return [
            'campaign_crew_id' => CampaignCrew::factory(),
            'character_id' => Character::factory(),
            'miniature_id' => null,
            'label' => null,
            'is_peon' => false,
            'title_group_key' => null,
            'acquired_week' => 1,
            'acquired_via' => 'hire',
            'granted_keyword_id' => null,
        ];
    }
}
