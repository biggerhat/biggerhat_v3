<?php

namespace App\Traits;

use App\Enums\UpgradeDomainTypeEnum;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UsesUpgrades
{
    public function upgrades(): MorphToMany
    {
        return $this->morphToMany(Upgrade::class, 'upgradeable');
    }

    public function crewUpgrades(): MorphToMany
    {
        return $this->upgrades()->where('domain', UpgradeDomainTypeEnum::Crew->value);
    }

    public function characterUpgrades(): MorphToMany
    {
        return $this->upgrades()->where('domain', UpgradeDomainTypeEnum::Character->value);
    }
}
