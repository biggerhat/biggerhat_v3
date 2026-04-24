<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\TriggerTimingEnum;
use App\Models\TOS\Trigger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trigger>
 */
class TriggerFactory extends Factory
{
    protected $model = Trigger::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'suits' => $this->faker->randomElement(['R', 'M', 'C', 'T', null]),
            'margin_cost' => null,
            'timing' => TriggerTimingEnum::Default,
            'body' => $this->faker->sentence(),
            'sort_order' => 0,
        ];
    }

    public function immediately(): static
    {
        return $this->state(fn () => ['timing' => TriggerTimingEnum::Immediately]);
    }

    public function marginCost(int $cost): static
    {
        return $this->state(fn () => ['margin_cost' => $cost, 'suits' => null]);
    }
}
