<?php

namespace Database\Factories;

use App\Models\TabletopImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TabletopImage>
 */
class TabletopImageFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'kind' => TabletopImage::KIND_TABLE,
            'terrain_key' => null,
            'image_path' => 'tabletop/'.Str::slug($name).'.webp',
            'credit' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function terrain(string $key = 'tower'): static
    {
        return $this->state(['kind' => TabletopImage::KIND_TERRAIN, 'terrain_key' => $key]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
