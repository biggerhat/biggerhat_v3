<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Keeps the notifications table bounded: read notifications go after a month, unread ones after a year
 * (anything unread that long is never going to be read).
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--read-days=30} {--unread-days=365}';

    protected $description = 'Delete old read and unread database notifications';

    public function handle(): int
    {
        $read = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays((int) $this->option('read-days')))
            ->delete();
        $unread = DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays((int) $this->option('unread-days')))
            ->delete();

        $this->info("Pruned {$read} read and {$unread} unread notifications.");

        return self::SUCCESS;
    }
}
