<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\LuckyMiss;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LuckyMiss>
 */
class LuckyMissFactory extends Factory
{
    protected $model = LuckyMiss::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(),
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_doppelganger' => false,
        ];
    }
}
