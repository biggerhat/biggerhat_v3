<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Activity-log variant for high-volume / user-driven models where we only
 * care about creation (e.g. "who started this game", "who made this crew")
 * — not every subsequent update. Avoids the noisy update stream you'd get
 * from logging every Game turn or every CrewBuild save.
 *
 * Spatie reads `static::$recordEvents` to limit which model events trigger
 * logging. Setting it to ['created'] disables the updated/deleted/restored
 * listeners.
 *
 * Use LogsAdminActivity instead when you also need update/delete history
 * (admin CRUDs).
 */
trait LogsCreationActivity
{
    use LogsActivity;

    /** @var array<int, string> */
    protected static array $recordEvents = ['created'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token', 'updated_at', 'created_at'])
            ->dontSubmitEmptyLogs()
            ->useLogName(strtolower(class_basename(static::class)));
    }
}
