<?php

namespace App\Traits;

use App\Enums\GameModeTypeEnum;
use Illuminate\Database\Eloquent\Builder;

trait HasGameModeType
{
    public function scopeForGameMode(Builder $query, GameModeTypeEnum $mode): Builder
    {
        return $query->where('game_mode_type', $mode->value);
    }

    public function scopeStandard(Builder $query): Builder
    {
        return $query->where('game_mode_type', GameModeTypeEnum::Standard->value);
    }
}
