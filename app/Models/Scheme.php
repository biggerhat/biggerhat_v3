<?php

namespace App\Models;

use App\Enums\PoolSeasonEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scheme extends Model
{
    /** @use HasFactory<\Database\Factories\SchemeFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'season' => PoolSeasonEnum::class,
        ];
    }
}
