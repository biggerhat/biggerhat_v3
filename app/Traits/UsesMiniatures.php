<?php

namespace App\Traits;

use App\Models\Miniature;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UsesMiniatures
{
    public function miniatures(): MorphToMany
    {
        return $this->morphToMany(Miniature::class, 'miniatureable');
    }
}
