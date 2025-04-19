<?php

namespace App\Models;

use App\Traits\UsesSelectOptionsScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Miniature extends Model
{
    /** @use HasFactory<\Database\Factories\MiniatureFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;

    protected $guarded = ['id'];

    //    protected static function bootSlugDisplayName(): void
    //    {
    //        static::creating(function (self $model) {
    //            $model->display_name = $model->name;
    //            if ($model->title) {
    //                $model->display_name .= ", {$model->title}";
    //            }
    //
    //            $model->slug = Str::slug($model->display_name);
    //        });
    //
    //        static::updating(function (self $model) {
    //            $model->display_name = $model->name;
    //            if ($model->title) {
    //                $model->display_name .= ", {$model->title}";
    //            }
    //
    //            $model->slug = Str::slug($model->display_name);
    //        });
    //    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
