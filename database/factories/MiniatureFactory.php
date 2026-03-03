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
        $name = $this->faker->name();
        $displayName = $name;
        $slug = Str::slug($displayName);

        return [
            'name' => $name,
            'display_name' => $displayName,
            'slug' => $slug,
            'front_image' => 'seed/card-front.png',
            'back_image' => 'seed/card-back.png',
            'combination_image' => 'seed/card-front.png',
            'version' => SculptVersionEnum::FourthEdition->value,
        ];
    }
}
