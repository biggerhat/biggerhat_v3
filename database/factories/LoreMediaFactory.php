<?php

namespace Database\Factories;

use App\Enums\LoreMediaTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoreMedia>
 */
class LoreMediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'type' => $this->faker->randomElement(LoreMediaTypeEnum::cases()),
            'link' => $this->faker->optional()->url(),
        ];
    }
}
