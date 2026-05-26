<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\CrewCardEffect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrewCardEffect>
 */
class CrewCardEffectFactory extends Factory
{
    protected $model = CrewCardEffect::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(20),
            'requires_token_choice' => false,
            'requires_marker_choice' => false,
            'requires_upgrade_type_choice' => false,
            'restrictions' => null,
            'grants_ability' => null,
            'grants_action' => null,
            'notes' => null,
        ];
    }
}
