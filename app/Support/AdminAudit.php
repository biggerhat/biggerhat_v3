<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * One-liner audit trail for admin/ops actions that aren't covered by a model's
 * LogsAdminActivity trait (role syncs, cache clears, impersonation, etc.).
 * Entries land in the shared `activity_log` table under the `admin` log name
 * so they show up on the Activity admin page next to model changes.
 */
class AdminAudit
{
    public const LOG_NAME = 'admin';

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $description, ?object $subject = null, array $properties = [], string $event = 'admin_action'): Activity
    {
        $logger = activity(self::LOG_NAME)->event($event);

        if ($subject instanceof Model) {
            $logger->performedOn($subject);
        }

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        if ($properties !== []) {
            $logger->withProperties($properties);
        }

        /** @var Activity $activity */
        $activity = $logger->log($description);

        return $activity;
    }
}
