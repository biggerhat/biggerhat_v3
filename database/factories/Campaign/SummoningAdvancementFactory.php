<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\SummoningAdvancement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SummoningAdvancement>
 */
class SummoningAdvancementFactory extends Factory
{
    protected $model = SummoningAdvancement::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(),
            'stat_block' => ['rg' => 8, 'skl' => 0, 'rst' => null, 'tn' => 8, 'dmg' => null],
        ];
    }
}
