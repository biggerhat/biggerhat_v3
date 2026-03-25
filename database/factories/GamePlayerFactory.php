<?php

namespace Database\Factories;

use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GamePlayer>
 */
class GamePlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => User::factory(),
            'slot' => 1,
            'faction' => $this->faker->randomElement(FactionEnum::cases()),
            'role' => $this->faker->randomElement(GameRoleEnum::cases()),
            'total_points' => 0,
            'soulstone_pool' => 0,
            'is_turn_complete' => false,
            'is_game_complete' => false,
        ];
    }

    public function slot(int $slot): static
    {
        return $this->state(['slot' => $slot]);
    }

    public function opponent(): static
    {
        return $this->state(['slot' => 2, 'user_id' => User::factory()]);
    }

    public function soloOpponent(): static
    {
        return $this->state(['slot' => 2, 'user_id' => null, 'opponent_name' => 'Opponent']);
    }
}
