<?php

use App\Models\BotHeartbeat;

Schedule::command('telescope:prune')->daily();
// Keep the activity_log table bounded (package default retention: 365 days).
Schedule::command('activitylog:clean')->daily();
// Discord bot monitoring: alert on missed heartbeats, keep the table bounded.
Schedule::command('bot:check-heartbeat')->everyMinute();
Schedule::command('model:prune', ['--model' => [BotHeartbeat::class]])->daily();
