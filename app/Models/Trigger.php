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
        ];
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_trigger');
    }
}
