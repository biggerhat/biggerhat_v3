<?php

namespace Database\Factories;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrewBuild>
 */
class CrewBuildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'faction' => $this->faker->randomElement(FactionEnum::cases()),
            'master_id' => Character::factory()->state(['station' => CharacterStationEnum::Master]),
            'encounter_size' => 50,
            'crew_data' => [$this->faker->randomNumber()],
            'is_archived' => false,
            'is_public' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(['is_archived' => true]);
    }

    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }
}
