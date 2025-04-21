<?php

namespace App\Models;

use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesUpgrades;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Action extends Model
{
    /** @use HasFactory<\Database\Factories\ActionFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesUpgrades;

    /**
     * @var array<string>|bool
     */
    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();
        self::bootSlugName();
    }

    protected static function bootSlugName(): void
    {
        static::creating(function (self $model) {
            $model->slug = Str::slug($model->name);
        });

        static::updating(function (self $model) {
            $model->slug = $model->id.'-'.Str::slug($model->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function triggers(): BelongsToMany
    {
        return $this->belongsToMany(Trigger::class, 'action_trigger');
    }

    public function characters(): MorphToMany
    {
        return $this->morphToMany(Character::class, 'characterable')->withPivot('is_signature_action');
    }
}
