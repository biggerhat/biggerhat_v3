<?php

namespace Database\Factories;

use App\Models\LoreMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lore>
 */
class LoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->sentence(4),
            'lore_media_id' => LoreMedia::factory(),
        ];
    }
}
