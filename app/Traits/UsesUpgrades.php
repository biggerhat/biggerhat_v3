<?php

namespace App\Traits;

use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UsesUpgrades
{
    public function upgrades(): MorphToMany
    {
        return $this->morphToMany(Upgrade::class, 'upgradeable');
    }
}
