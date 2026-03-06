<?php

namespace App\Traits;

use App\Models\Miniature;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UsesMiniatures
{
    /** @return MorphToMany<Miniature, $this> */
    public function miniatures(): MorphToMany
    {
        return $this->morphToMany(Miniature::class, 'miniatureable');
    }
}
