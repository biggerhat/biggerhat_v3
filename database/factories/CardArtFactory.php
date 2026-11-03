<?php

namespace Database\Factories;

use App\Enums\FactionEnum;
use App\Models\CardArt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CardArt>
 */
class CardArtFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'faction' => null,
            'category' => $this->faker->randomElement(['Human', 'Construct', 'Beast', 'Undead']),
            'image_path' => 'card_arts/'.Str::slug($name).'.webp',
            'credit' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forFaction(FactionEnum $faction): static
    {
        return $this->state(['faction' => $faction->value]);
    }
}
