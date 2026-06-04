<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignEquipment>
 */
class CampaignEquipmentFactory extends Factory
{
    protected $model = CampaignEquipment::class;

    public function definition(): array
    {
        return [
            'campaign_crew_id' => CampaignCrew::factory(),
            'equipment_upgrade_id' => Upgrade::factory()->campaignEquipment(),
            'source' => 'barter',
            'acquired_aftermath_id' => null,
        ];
    }
}
