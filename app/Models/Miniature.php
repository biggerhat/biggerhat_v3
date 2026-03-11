<?php

namespace App\Models;

use App\Observers\MiniatureObserver;
use App\Traits\UsesSelectOptionsScope;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin IdeHelperMiniature
 */
#[ObservedBy(MiniatureObserver::class)]
class Miniature extends Model
{
    /** @use HasFactory<\Database\Factories\MiniatureFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;

    protected $guarded = ['id'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id', 'id');
    }

    public function blueprints(): MorphToMany
    {
        return $this->morphedByMany(Blueprint::class, 'miniatureable');
    }

    public function packages(): MorphToMany
    {
        return $this->morphedByMany(Package::class, 'miniatureable');
    }
}
