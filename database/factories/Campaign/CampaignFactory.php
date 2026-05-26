<?php

namespace Database\Factories\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignPlayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name' => 'The '.$this->faker->word.' Campaign',
            'length_weeks' => $this->faker->numberBetween(4, 12),
            'current_week' => 1,
            'organizer_user_id' => User::factory(),
            'status' => CampaignStatusEnum::Planning,
            'optional_rules' => [],
            'competitive' => false,
            'weekly_event_active' => false,
            'is_solo' => false,
        ];
    }

    public function solo(): static
    {
        return $this->state(fn () => ['is_solo' => true]);
    }

    /**
     * Adds the organizer as a Player row (the controller does this on create —
     * factory mirrors it for self-contained test setup).
     */
    public function withOrganizerMembership(): static
    {
        return $this->afterCreating(function (Campaign $campaign) {
            CampaignPlayer::factory()->create([
                'campaign_id' => $campaign->id,
                'user_id' => $campaign->organizer_user_id,
                'role' => CampaignPlayerRoleEnum::Organizer,
            ]);
        });
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatusEnum::Active,
            'started_at' => now(),
        ]);
    }
}
