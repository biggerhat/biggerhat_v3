<?php

namespace Database\Factories;

use App\Models\TabletopLayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabletopLayout>
 */
class TabletopLayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'description' => null,
            'terrain' => [
                ['id' => 'p1', 'name' => 'Tower', 'kind' => 'tower', 'traits' => ['blocking', 'climbable'], 'surfaces' => [
                    ['id' => 'p1-1', 'top' => 3, 'shape' => ['kind' => 'rect', 'x' => 4, 'y' => 4, 'w' => 6, 'h' => 5]],
                ]],
            ],
            'table_image' => null,
            'is_shared' => false,
            'copied_from' => null,
        ];
    }

    public function shared(): static
    {
        return $this->state(['is_shared' => true]);
    }
}
