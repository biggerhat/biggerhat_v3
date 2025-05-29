<?php

namespace App\Models;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperStrategy
 */
class Strategy extends Model
{
    /** @use HasFactory<\Database\Factories\StrategyFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'season' => PoolSeasonEnum::class,
            'suit' => SuitEnum::class,
        ];
    }
}
