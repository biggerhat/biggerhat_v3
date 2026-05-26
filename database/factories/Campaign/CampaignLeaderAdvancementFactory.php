<?php

namespace Database\Factories\Campaign;

use App\Enums\AdvancementTableEnum;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\CustomCharacter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignLeaderAdvancement>
 */
class CampaignLeaderAdvancementFactory extends Factory
{
    protected $model = CampaignLeaderAdvancement::class;

    public function definition(): array
    {
        return [
            'custom_character_id' => CustomCharacter::factory(),
            'source_aftermath_id' => null,
            'source_table' => AdvancementTableEnum::AttackMod->value,
            'catalog_id' => null,
            'applied_to_action_index' => -1,
            'applied_to_custom_character_id' => null,
            'position_in_xp_track' => 0,
            'free_choice' => null,
            'acquired_at' => now(),
        ];
    }
}
