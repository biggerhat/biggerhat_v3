<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignLeaderXpTrack;
use App\Models\CustomCharacter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignLeaderXpTrack>
 */
class CampaignLeaderXpTrackFactory extends Factory
{
    protected $model = CampaignLeaderXpTrack::class;

    public function definition(): array
    {
        return [
            'custom_character_id' => CustomCharacter::factory(),
            'track' => CampaignLeaderXpTrack::defaultTrack(),
        ];
    }
}
