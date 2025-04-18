<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UsesSlugName
{
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
            $model->slug = Str::slug($model->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
