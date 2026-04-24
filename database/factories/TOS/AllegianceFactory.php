<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Allegiance>
 */
class AllegianceFactory extends Factory
{
    protected $model = Allegiance::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'short_name' => null,
            'type' => AllegianceTypeEnum::Earth,
            'is_syndicate' => false,
            'description' => $this->faker->paragraph(),
            'logo_path' => null,
            'color_slug' => null,
            'sort_order' => 0,
        ];
    }

    public function syndicate(): static
    {
        return $this->state(fn () => ['is_syndicate' => true]);
    }

    public function earth(): static
    {
        return $this->state(fn () => ['type' => AllegianceTypeEnum::Earth]);
    }

    public function malifaux(): static
    {
        return $this->state(fn () => ['type' => AllegianceTypeEnum::Malifaux]);
    }
}
