<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignInvitation>
 */
class CampaignInvitationFactory extends Factory
{
    protected $model = CampaignInvitation::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'user_id' => User::factory(),
            'email' => null,
            'token' => Str::random(32),
            'accepted_at' => null,
            'expires_at' => now()->addWeek(),
        ];
    }

    public function byEmail(string $email): static
    {
        return $this->state(fn () => ['user_id' => null, 'email' => $email]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
