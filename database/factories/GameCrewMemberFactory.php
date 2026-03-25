<?php

namespace Database\Factories;

use App\Enums\FactionEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameCrewMember>
 */
class GameCrewMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'game_player_id' => GamePlayer::factory(),
            'character_id' => \App\Models\Character::factory(),
            'display_name' => $this->faker->name(),
            'faction' => $this->faker->randomElement(FactionEnum::cases())->value,
            'current_health' => 6,
            'max_health' => 6,
            'cost' => $this->faker->numberBetween(4, 10),
            'station' => 'minion',
            'hiring_category' => 'in-keyword',
            'is_killed' => false,
            'is_summoned' => false,
            'is_activated' => false,
            'attached_upgrades' => [],
            'attached_tokens' => [],
            'attached_markers' => [],
            'sort_order' => 0,
        ];
    }

    public function killed(): static
    {
        return $this->state(['is_killed' => true, 'current_health' => 0]);
    }

    public function summoned(): static
    {
        return $this->state(['is_summoned' => true, 'hiring_category' => 'summoned']);
    }
}
