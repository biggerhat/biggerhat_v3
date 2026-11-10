<?php

namespace Database\Factories\TOS;

use App\Models\TOS\Operation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operation>
 */
class OperationFactory extends Factory
{
    protected $model = Operation::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'source' => 'Fields of Glory v3.0',
            'flavor' => $this->faker->sentence(),
            'additional_setup' => null,
            'special_rules' => $this->faker->paragraph(),
            'victory_conditions' => 'When a Commander unit is Killed, the opposing Company scores 2 Victory Points.',
            'image_path' => null,
            'sort_order' => 0,
        ];
    }

    public function fromCoreRulebook(): static
    {
        return $this->state(fn () => ['source' => 'Core Rulebook']);
    }
}
