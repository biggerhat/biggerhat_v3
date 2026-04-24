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

    /**
     * Attach this trigger to one or more Actions via the pivot.
     * Useful in tests that need "trigger on action X".
     */
    public function forActions(\App\Models\TOS\Action ...$actions): static
    {
        return $this->afterCreating(function (\App\Models\TOS\Trigger $trigger) use ($actions) {
            foreach ($actions as $i => $action) {
                $trigger->actions()->attach($action->id, ['sort_order' => $i]);
            }
        });
    }
}
