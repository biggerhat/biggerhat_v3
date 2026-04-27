<?php

namespace App\Traits;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Drop-in for any model an admin edits via the admin CRUD. Records create /
 * update / delete events with the dirty fields, attributed to the acting user
 * via Spatie's default causer resolution. Models that need per-field tweaks
 * (sensitive columns, larger logs, etc.) can override getActivitylogOptions().
 */
trait LogsAdminActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // logAll() works regardless of $fillable/$guarded conventions, which
        // matters here because most models in this codebase use $guarded.
        // Skips noise (timestamps, password fields) by default.
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token', 'updated_at', 'created_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(strtolower(class_basename(static::class)));
    }
}
