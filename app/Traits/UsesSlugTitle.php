<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UsesSlugTitle
{
    protected static function bootSlugTitle(): void
    {
        static::creating(function (self $model) {
            $model->slug = Str::slug($model->title);
        });

        static::updating(function (self $model) {
            $model->slug = Str::slug($model->title);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
