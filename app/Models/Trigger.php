<?php

namespace App\Models;

use App\Enums\GameModeTypeEnum;
use App\Traits\HasGameModeType;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperTrigger
 */
class Trigger extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerFactory> */
    use HasFactory;

    use HasGameModeType;
    use UsesSelectOptionsScope;
    use UsesSlugName;

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
            'campaign_grants_signature' => 'boolean',
        ];
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_trigger');
    }
}
