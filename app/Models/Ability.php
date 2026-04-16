<?php

namespace App\Models;

use App\Enums\GameModeTypeEnum;
use App\Traits\HasGameModeType;
use App\Traits\UsesCharacters;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use App\Traits\UsesUpgrades;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAbility
 */
class Ability extends Model
{
    /** @use HasFactory<\Database\Factories\AbilityFactory> */
    use HasFactory;

    use HasGameModeType;
    use UsesCharacters;
    use UsesSelectOptionsScope;
    use UsesSlugName;
    use UsesUpgrades;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'game_mode_type' => GameModeTypeEnum::class,
        ];
    }
}
