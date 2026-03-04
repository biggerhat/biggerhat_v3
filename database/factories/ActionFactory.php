<?php

namespace Database\Factories;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\ModifierTypeEnum;
use App\Enums\ResistanceTypeEnum;
use App\Enums\SuitEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Action>
 */
class ActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(ActionTypeEnum::cases());
        $hasRange = $this->faker->boolean(70);
        $hasStat = $this->faker->boolean(80);
        $hasTarget = $this->faker->boolean(50);

        return [
            'name' => $this->faker->unique()->words(random_int(1, 3), true),
            'type' => $type,
            'is_signature' => $this->faker->boolean(15),
            'costs_stone' => $this->faker->boolean(20),
            'range' => $hasRange ? random_int(0, 14) : null,
            'range_type' => $hasRange ? $this->faker->randomElement(ActionRangeTypeEnum::cases()) : null,
            'stat' => $hasStat ? random_int(1, 8) : null,
            'stat_suits' => $hasStat ? $this->faker->optional(0.3)->randomElement(SuitEnum::cases()) : null,
            'stat_modifier' => $hasStat ? $this->faker->optional(0.2)->randomElement(ModifierTypeEnum::cases()) : null,
            'resisted_by' => $hasStat ? $this->faker->optional(0.6)->randomElement(ResistanceTypeEnum::cases()) : null,
            'target_number' => $hasTarget ? random_int(5, 16) : null,
            'target_suits' => $hasTarget ? $this->faker->optional(0.3)->randomElement(SuitEnum::cases()) : null,
            'damage' => $type === ActionTypeEnum::Attack ? random_int(1, 5) : null,
            'description' => $this->faker->optional(0.7)->sentence(random_int(5, 20)),
        ];
    }
}
