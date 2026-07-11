<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CampaignCrewCard;
use App\Models\Character;
use App\Models\CustomCharacter;
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

    /** The card is printed on an official master (sets master_id + the matching morph type). */
    public function forOfficialMaster(Character $master): static
    {
        return $this->state(fn () => ['master_id' => $master->id, 'master_type' => Character::class]);
    }

    /** The card is printed on a custom-built Campaign Leader (sets master_id + the matching morph type). */
    public function forCustomMaster(CustomCharacter $master): static
    {
        return $this->state(fn () => ['master_id' => $master->id, 'master_type' => CustomCharacter::class]);
    }
}
