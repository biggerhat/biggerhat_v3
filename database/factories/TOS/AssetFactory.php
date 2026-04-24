<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AssetLimitParameterTypeEnum;
use App\Enums\TOS\AssetLimitTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Asset;
use App\Models\TOS\AssetLimit;
use App\Models\TOS\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(random_int(2, 3), true);

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name' => Str::title($name),
            'scrip_cost' => $this->faker->numberBetween(1, 6),
            'disable_count' => $this->faker->optional(0.5)->numberBetween(1, 3),
            'scrap_count' => $this->faker->optional(0.3)->numberBetween(1, 3),
            'body' => $this->faker->sentence(12),
            'image_path' => null,
            'sort_order' => 0,
        ];
    }

    public function forAllegiance(Allegiance $allegiance): static
    {
        return $this->afterCreating(fn (Asset $a) => $a->allegiances()->syncWithoutDetaching([$allegiance->id]));
    }

    public function restrictedByUnit(Unit $unit): static
    {
        return $this->afterCreating(fn (Asset $a) => AssetLimit::create([
            'asset_id' => $a->id,
            'limit_type' => AssetLimitTypeEnum::Restricted,
            'parameter_type' => AssetLimitParameterTypeEnum::UnitName,
            'parameter_value' => $unit->slug,
            'parameter_unit_id' => $unit->id,
        ]));
    }

    public function restrictedByUnitType(string $typeSlug): static
    {
        return $this->afterCreating(fn (Asset $a) => AssetLimit::create([
            'asset_id' => $a->id,
            'limit_type' => AssetLimitTypeEnum::Restricted,
            'parameter_type' => AssetLimitParameterTypeEnum::UnitType,
            'parameter_value' => $typeSlug,
        ]));
    }

    public function restrictedByAllegiance(Allegiance $allegiance): static
    {
        return $this->afterCreating(fn (Asset $a) => AssetLimit::create([
            'asset_id' => $a->id,
            'limit_type' => AssetLimitTypeEnum::Restricted,
            'parameter_type' => AssetLimitParameterTypeEnum::Allegiance,
            'parameter_value' => $allegiance->slug,
            'parameter_allegiance_id' => $allegiance->id,
        ]));
    }

    public function slot(string $location): static
    {
        return $this->afterCreating(fn (Asset $a) => AssetLimit::create([
            'asset_id' => $a->id,
            'limit_type' => AssetLimitTypeEnum::Slot,
            'parameter_type' => AssetLimitParameterTypeEnum::Location,
            'parameter_value' => $location,
        ]));
    }

    public function unique(): static
    {
        return $this->afterCreating(fn (Asset $a) => AssetLimit::create([
            'asset_id' => $a->id,
            'limit_type' => AssetLimitTypeEnum::Unique,
        ]));
    }

    public function adjunct(int $sizeMm): static
    {
        return $this->afterCreating(fn (Asset $a) => AssetLimit::create([
            'asset_id' => $a->id,
            'limit_type' => AssetLimitTypeEnum::Adjunct,
            'parameter_type' => AssetLimitParameterTypeEnum::SizeMm,
            'parameter_value' => (string) $sizeMm,
        ]));
    }
}
