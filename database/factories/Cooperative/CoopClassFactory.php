<?php

namespace Database\Factories\Cooperative;

use App\Enums\CoopRoleEnum;
use App\Models\Cooperative\CoopClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CoopClass>
 */
class CoopClassFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'role' => CoopRoleEnum::Brawler,
            'is_unique' => false,
            'resistance' => 10,
            'speed' => 6,
            'max_health' => 6,
            'elite_max_health' => 11,
            'abilities' => [['name' => 'Armor', 'text' => 'Reduce damage dealt to this model by 1.']],
            'actions' => [[
                'kind' => 'attack', 'name' => 'Smashing Strikes', 'rg' => '2"', 'skl' => '12', 'rst' => 'Df', 'tn' => '-', 'dmg' => '2',
                'text' => '', 'triggers' => [['name' => 'Cruelty', 'text' => 'Once per turn. Declare this action again.']],
            ]],
            'behavior' => [
                ['flip_min' => 10, 'flip_max' => null, 'ideal_range' => '0"', 'effects' => ['Walk', 'Smashing Strikes', 'Smashing Strikes with +2 Dmg']],
                ['flip_min' => 1, 'flip_max' => 9, 'ideal_range' => '0"', 'effects' => ['Walk', 'Smashing Strikes']],
            ],
            'target_preference' => ['Most wounded', 'Lowest cost', 'Closest'],
            'elite_modifications' => 'Maximum health +5. This model deals +1 Dmg.',
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function role(CoopRoleEnum $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
