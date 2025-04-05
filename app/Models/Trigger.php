<?php

namespace App\Models;

use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trigger extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerFactory> */
    use HasFactory;

    use UsesSlugName;

    protected $guarded = ['id'];

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_trigger');
    }
}
