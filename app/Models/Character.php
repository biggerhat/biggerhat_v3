<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Character extends Model
{
    /** @use HasFactory<\Database\Factories\CharacterFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function bootSlugTitle(): void
    {
        static::creating(function (self $model) {
            $model->slug = Str::slug($model->display_name);
        });

        static::updating(function (self $model) {
            $model->slug = Str::slug($model->display_name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
