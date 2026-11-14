<?php

use App\Models\BotHeartbeat;

Schedule::command('telescope:prune')->daily();
// Keep the activity_log table bounded (package default retention: 365 days).
Schedule::command('activitylog:clean')->daily();
// Discord bot monitoring: alert on missed heartbeats, keep the table bounded.
Schedule::command('bot:check-heartbeat')->everyMinute();
Schedule::command('model:prune', ['--model' => [BotHeartbeat::class]])->daily();
// Players with an unfinished game hear when their round enters its last-turn window.
Schedule::command('tournaments:round-time-warnings')->everyMinute();
// Housekeeping: Horizon's metrics graphs stay empty without snapshots, and failed jobs / finished batches / read
// notifications otherwise grow forever.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->daily();
Schedule::command('queue:prune-batches', ['--hours' => 48])->daily();
Schedule::command('notifications:prune')->daily();
// Two-player games that never got an opponent.
Schedule::command('games:prune-stale-lobbies')->daily();
