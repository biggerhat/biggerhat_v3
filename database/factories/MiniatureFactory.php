<?php

namespace Database\Factories;

use App\Enums\SculptVersionEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Miniature>
 */
class MiniatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Most miniatures have no name (inherit character name); ~15% get their own
        $name = $this->faker->boolean(15) ? $this->faker->name() : null;

        return [
            'name' => $name,
            'display_name' => $name ?? 'placeholder',
            'slug' => Str::slug($name ?? 'placeholder'),
            'front_image' => 'seed/card-front.png',
            'back_image' => 'seed/card-back.png',
            'combination_image' => 'seed/card-front.png',
            'version' => SculptVersionEnum::FourthEdition->value,
        ];
    }
}
