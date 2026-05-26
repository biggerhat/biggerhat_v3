<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\AdvancementAbility;

class AdvancementAbilityFactory extends BaseAdvancementFactory
{
    protected $model = AdvancementAbility::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'modifier_type' => 'choice',
            'suit' => null,
        ];
    }
}
