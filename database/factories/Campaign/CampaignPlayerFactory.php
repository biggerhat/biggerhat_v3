<?php

namespace Database\Factories\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignPlayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignPlayer>
 */
class CampaignPlayerFactory extends Factory
{
    protected $model = CampaignPlayer::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'user_id' => User::factory(),
            'role' => CampaignPlayerRoleEnum::Player,
        ];
    }

    public function organizer(): static
    {
        return $this->state(fn () => ['role' => CampaignPlayerRoleEnum::Organizer]);
    }
}
