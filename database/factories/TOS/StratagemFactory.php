<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Stratagem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Stratagem>
 */
class StratagemFactory extends Factory
{
    protected $model = Stratagem::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'allegiance_id' => null,
            'allegiance_type' => AllegianceTypeEnum::Earth,
            'tactical_cost' => $this->faker->numberBetween(1, 3),
            'effect' => $this->faker->paragraph(),
            'image_path' => null,
            'sort_order' => 0,
        ];
    }

    public function forAllegiance(Allegiance $allegiance): static
    {
        return $this->state(fn () => [
            'allegiance_id' => $allegiance->id,
            'allegiance_type' => $allegiance->type,
        ]);
    }

    public function forType(AllegianceTypeEnum $type): static
    {
        return $this->state(fn () => [
            'allegiance_id' => null,
            'allegiance_type' => $type,
        ]);
    }
}
