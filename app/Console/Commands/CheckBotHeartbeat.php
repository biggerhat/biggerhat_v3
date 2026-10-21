<?php

namespace App\Console\Commands;

use App\Models\BotHeartbeat;
use App\Models\User;
use App\Notifications\BotStatusChanged;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CheckBotHeartbeat extends Command
{
    public const OFFLINE_FLAG = 'bot:alerted_offline';

    protected $signature = 'bot:check-heartbeat';

    protected $description = 'Alert super admins when the Discord bot stops (or resumes) sending heartbeats';

    public function handle(): int
    {
        $last = BotHeartbeat::query()->latest('reported_at')->first();
        if (! $last) {
            return self::SUCCESS; // Never reported: telemetry isn't set up yet.
        }

        $minutes = max(1, (int) config('services.bot.offline_after_minutes', 3));
        $stale = $last->reported_at->lt(now()->subMinutes($minutes));
        $alerted = Cache::has(self::OFFLINE_FLAG);

        if ($stale && ! $alerted) {
            Cache::forever(self::OFFLINE_FLAG, true);
            $this->announce(new BotStatusChanged(false, $last->reported_at->toDayDateTimeString().' UTC'));
        } elseif (! $stale && $alerted) {
            Cache::forget(self::OFFLINE_FLAG);
            $this->announce(new BotStatusChanged(true));
        }

        return self::SUCCESS;
    }

    private function announce(BotStatusChanged $notification): void
    {
        Notification::send(User::role('super_admin')->get(), $notification);

        $webhook = config('services.bot.alert_webhook');
        if ($webhook) {
            try {
                Http::timeout(5)->post($webhook, ['content' => ($notification->online ? '✅ ' : '🚨 ').$notification->message()]);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
