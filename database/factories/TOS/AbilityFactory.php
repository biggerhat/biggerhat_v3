<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\UsageLimitEnum;
use App\Models\TOS\Ability;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ability>
 */
class AbilityFactory extends Factory
{
    protected $model = Ability::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(1, 2), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'body' => $this->faker->sentence(random_int(8, 20)),
            'is_general' => false,
            'allegiance_id' => null,
            'usage_limit' => null,
        ];
    }

    public function general(): static
    {
        return $this->state(fn () => ['is_general' => true, 'allegiance_id' => null]);
    }

    public function oncePer(UsageLimitEnum $limit): static
    {
        return $this->state(fn () => ['usage_limit' => $limit->value]);
    }
}
