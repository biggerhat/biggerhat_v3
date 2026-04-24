<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\SpecialUnitRuleEnum;
use App\Models\TOS\SpecialUnitRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpecialUnitRule>
 */
class SpecialUnitRuleFactory extends Factory
{
    protected $model = SpecialUnitRule::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'description' => $this->faker->sentence(),
            'sort_order' => 0,
        ];
    }

    public function forCanonical(SpecialUnitRuleEnum $rule): static
    {
        return $this->state(fn () => [
            'slug' => $rule->value,
            'name' => $rule->label(),
        ]);
    }
}
