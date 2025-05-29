<?php

namespace App\Models;

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

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'action_trigger');
    }
}
