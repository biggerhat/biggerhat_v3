<?php

namespace Database\Factories;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Character>
 */
class CharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function definition(): array
    {
        $name = $this->faker->name();
        $title = $this->faker->optional(0.05)->sentence(3);
        $displayName = $name;
        if ($title) {
            $displayName .= ', '.$title;
        }
        $slug = Str::slug($displayName);

        return [
            'name' => $name,
            'title' => $title,
            'display_name' => $displayName,
            'slug' => $slug,
            'nicknames' => $this->faker->optional(0.1)->sentence(random_int(1, 4)),
            'faction' => $this->faker->randomElement(FactionEnum::cases()),
            'station' => $this->faker->randomElement(CharacterStationEnum::cases()),
            'cost' => random_int(1, 10),
            'health' => random_int(1, 15),
            'size' => random_int(1, 3),
            'base' => $this->faker->randomElement(BaseSizeEnum::cases()),
            'defense' => random_int(1, 7),
            'willpower' => random_int(1, 7),
            'speed' => random_int(1, 7),
            'count' => random_int(1, 5),
            'summon_target_number' => $this->faker->optional(0.15)->randomNumber(1, 10),
        ];
    }
}
