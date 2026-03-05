<?php

namespace App\Traits;

use App\Models\Package;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UsesPackages
{
    public function packages(): MorphToMany
    {
        return $this->morphToMany(Package::class, 'packageable');
    }
}
