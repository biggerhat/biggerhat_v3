<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\UsageLimitEnum;
use App\Models\TOS\Action;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Action>
 */
class ActionFactory extends Factory
{
    protected $model = Action::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'av' => $this->faker->numberBetween(4, 7),
            'av_target' => $this->faker->randomElement(['Df', 'Wp', null]),
            'av_suits' => null,
            'tn' => null,
            'range' => $this->faker->randomElement(['y', '6"', '8"', '10"']),
            'strength' => $this->faker->optional(0.6)->numberBetween(1, 4),
            'is_piercing' => false,
            'is_accurate' => false,
            'is_area' => false,
            'usage_limit' => null,
            'body' => $this->faker->sentence(random_int(6, 15)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Action $action) {
            // Default to a single Melee type so un-stated factory builds still
            // resolve in relation queries. Callers override via withTypes().
            if ($action->typeLinks()->count() === 0) {
                $action->syncTypes([ActionTypeEnum::Melee]);
            }
        });
    }

    public function magic(): static
    {
        return $this->withTypes(ActionTypeEnum::Magic);
    }

    public function melee(): static
    {
        return $this->withTypes(ActionTypeEnum::Melee)->state(fn () => ['range' => 'y']);
    }

    public function missile(): static
    {
        return $this->withTypes(ActionTypeEnum::Missile);
    }

    public function morale(): static
    {
        return $this->withTypes(ActionTypeEnum::Morale);
    }

    public function withTypes(ActionTypeEnum ...$types): static
    {
        $all = $types;

        return $this->afterCreating(fn (Action $a) => $a->syncTypes($all));
    }

    public function strength(int $s): static
    {
        return $this->state(fn () => ['strength' => $s]);
    }

    public function avSuits(string $suits): static
    {
        return $this->state(fn () => ['av_suits' => $suits]);
    }

    public function piercing(): static
    {
        return $this->state(fn () => ['is_piercing' => true]);
    }

    public function accurate(): static
    {
        return $this->state(fn () => ['is_accurate' => true]);
    }

    public function area(): static
    {
        return $this->state(fn () => ['is_area' => true]);
    }

    public function oncePer(UsageLimitEnum $limit): static
    {
        return $this->state(fn () => ['usage_limit' => $limit->value]);
    }
}
