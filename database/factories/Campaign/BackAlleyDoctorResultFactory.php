<?php

namespace Database\Factories\Campaign;

use App\Models\Campaign\BackAlleyDoctorResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackAlleyDoctorResult>
 */
class BackAlleyDoctorResultFactory extends Factory
{
    protected $model = BackAlleyDoctorResult::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'body' => $this->faker->sentence(),
            'flip_value_min' => 1,
            'flip_value_max' => 8,
            'is_black_joker' => false,
            'is_red_joker' => false,
            'outcome_kind' => 'no_effect',
        ];
    }
}
