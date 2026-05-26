<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\WeeklyEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyEvent>
 */
class WeeklyEventFactory extends Factory
{
    protected $model = WeeklyEvent::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(),
            'flip_value' => $this->faker->numberBetween(1, 13),
            'is_black_joker' => false,
            'is_red_joker' => false,
            'terrain_marker_def' => null,
            'requires_placement' => false,
            'is_one_time' => false,
        ];
    }
}
