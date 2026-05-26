<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\AdvancementAction;

class AdvancementActionFactory extends BaseAdvancementFactory
{
    protected $model = AdvancementAction::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'modifier_type' => 'choice',
            'suit' => null,
            'stat_block' => ['rg' => 8, 'skl' => 5, 'rst' => 'df', 'tn' => null, 'dmg' => 2],
        ];
    }
}
