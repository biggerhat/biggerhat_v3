<?php

namespace Database\Factories;

use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $factions = $this->faker->randomElements(
            FactionEnum::cases(),
            $this->faker->numberBetween(1, 2)
        );

        return [
            'name' => $this->faker->unique()->words(random_int(2, 4), true),
            'description' => $this->faker->optional(0.5)->paragraph(),
            'factions' => collect($factions)->map(fn (FactionEnum $f) => $f->value)->toArray(),
            'sku' => $this->faker->optional(0.7)->bothify('WYR##-###'),
            'upc' => $this->faker->optional(0.3)->ean13(),
            'msrp' => $this->faker->optional(0.7)->randomElement([2500, 3500, 4500, 5500, 6500]),
            'distributor_description' => $this->faker->optional(0.3)->sentence(),
            'sculpt_version' => $this->faker->randomElement(SculptVersionEnum::cases())->value,
            'is_preassembled' => $this->faker->boolean(20),
            'released_at' => $this->faker->optional(0.6)->dateTimeBetween('-5 years', 'now'),
        ];
    }
}
