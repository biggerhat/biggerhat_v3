<?php

namespace App\Traits;

use App\Models\Character;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UsesCharacters
{
    public function characters(): MorphToMany
    {
        return $this->morphToMany(Character::class, 'characterable');
    }
}
