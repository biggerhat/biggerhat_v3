<?php

namespace Database\Factories\TOS;

use App\Enums\TOS\AssetLimitTypeEnum;
use App\Models\TOS\Asset;
use App\Models\TOS\AssetLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetLimit>
 */
class AssetLimitFactory extends Factory
{
    protected $model = AssetLimit::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'limit_type' => AssetLimitTypeEnum::Unique,
            'parameter_type' => null,
            'parameter_value' => null,
            'parameter_unit_id' => null,
            'parameter_allegiance_id' => null,
            'notes' => null,
        ];
    }
}
