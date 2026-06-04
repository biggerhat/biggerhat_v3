<?php

namespace Database\Factories;

use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trigger>
 */
class TriggerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(random_int(1, 3), true),
            'suits' => $this->faker->optional(0.8)->randomElement(SuitEnum::cases()),
            'stone_cost' => $this->faker->numberBetween(0, 2),
            'description' => $this->faker->optional(0.8)->sentence(random_int(5, 15)),
        ];
    }

    /**
     * Campaign Mode attack-mod advancement — Trigger row with
     * campaign_advancement_kind='attack' + flip-value gating.
     */
    public function campaignAdvancementAttack(): static
    {
        return $this->state(fn () => [
            'game_mode_type' => GameModeTypeEnum::Campaign,
            'campaign_advancement_kind' => 'attack',
            'campaign_modifier_type' => 'trigger',
            'campaign_flip_value' => $this->faker->numberBetween(1, 13),
            'campaign_is_always_available' => false,
        ]);
    }

    /** Campaign Mode tactical-mod advancement. */
    public function campaignAdvancementTactical(): static
    {
        return $this->campaignAdvancementAttack()->state(fn () => [
            'campaign_advancement_kind' => 'tactical',
        ]);
    }
}
