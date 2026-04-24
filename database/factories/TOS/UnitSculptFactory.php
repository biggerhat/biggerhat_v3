<?php

namespace Database\Factories\TOS;

use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnitSculpt>
 */
class UnitSculptFactory extends Factory
{
    protected $model = UnitSculpt::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'front_image' => null,
            'back_image' => null,
            'combination_image' => null,
            'release_date' => null,
            'box_reference' => null,
            'sort_order' => 0,
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->for($unit, 'unit');
    }
}
