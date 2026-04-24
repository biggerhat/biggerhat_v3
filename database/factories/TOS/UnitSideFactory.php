<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\UnitSideEnum;
use App\Models\TOS\UnitSide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitSide>
 */
class UnitSideFactory extends Factory
{
    protected $model = UnitSide::class;

    public function definition(): array
    {
        return [
            'side' => UnitSideEnum::Standard,
            'speed' => $this->faker->numberBetween(4, 8),
            'defense' => $this->faker->numberBetween(3, 6),
            'willpower' => $this->faker->numberBetween(3, 6),
            'armor' => $this->faker->numberBetween(0, 3),
        ];
    }

    public function standard(): static
    {
        return $this->state(fn () => ['side' => UnitSideEnum::Standard]);
    }

    public function glory(): static
    {
        return $this->state(fn () => ['side' => UnitSideEnum::Glory]);
    }
}
