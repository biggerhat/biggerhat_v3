<?php

namespace App\Traits\TOS;

use Illuminate\Support\Str;

/**
 * Auto-populates the `slug` column on `creating` when none is set, so TOS
 * admin controllers don't each repeat `Str::slug(...)` calls. Mirrors the
 * Malifaux Observer pattern (`CharacterObserver::creating`) but scoped to
 * slug-only since most TOS models have no other derived fields.
 *
 * Models that would produce duplicate slugs from duplicate names (e.g. two
 * "Attack" actions) keep the existing random-suffix disambiguation; models
 * with canonical/unique names override `slugNeedsRandomSuffix()` to return
 * false for deterministic permalinks.
 *
 * Intentionally only fires on `creating` — updating the name must not break
 * existing permalinks, matching the behavior consumers see on the public URLs.
 */
trait GeneratesTosSlug
{
    protected static function bootGeneratesTosSlug(): void
    {
        static::creating(function ($model) {
            if ($model->slug) {
                return;
            }

            $base = Str::slug($model->name);
            $model->slug = static::slugNeedsRandomSuffix()
                ? $base.'-'.Str::random(4)
                : $base;
        });
    }

    protected static function slugNeedsRandomSuffix(): bool
    {
        return true;
    }
}
