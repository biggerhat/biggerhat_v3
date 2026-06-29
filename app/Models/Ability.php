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
            // Cast campaign boolean flags so they round-trip as real booleans —
            // otherwise they reach the admin form as ints and break the radix
            // checkbox state (see Upgrade model / UpgradeCampaignFlagsTest).
            'campaign_is_always_available' => 'boolean',
            'campaign_joker_freechoice' => 'boolean',
        ];
    }
}
