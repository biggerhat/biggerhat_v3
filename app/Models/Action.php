<?php

namespace App\Models;

use App\Traits\UsesCharacters;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Action extends Model
{
    /** @use HasFactory<\Database\Factories\ActionFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function triggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'action_trigger');
    }
}
