<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotHeartbeat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Response;
use Inertia\ResponseFactory;

class BotMonitorAdminController extends Controller
{
    private const WINDOW_HOURS = 24;

    private const RECENT_ERRORS = 30;

    public function index(Request $request): Response|ResponseFactory
    {
        $latest = BotHeartbeat::query()->latest('reported_at')->first();
        $since = now()->subHours(self::WINDOW_HOURS);
        $rows = BotHeartbeat::query()->where('reported_at', '>=', $since)->orderBy('reported_at')->get();

        return inertia('Admin/BotMonitor/Index', [
            'status' => $this->status($latest),
            'latest' => $latest ? [
                'reported_at' => $latest->reported_at->toIso8601String(),
                'bot_version' => $latest->bot_version,
                'uptime_seconds' => $latest->uptime_seconds,
                'ws_ping_ms' => $latest->ws_ping_ms,
                'guild_count' => $latest->guild_count,
                'memory_mb' => $latest->memory_mb,
            ] : null,
            'totals' => $this->totals($rows),
            'hourly' => $this->hourly($rows),
            'top_commands' => $this->topCommands($rows),
            'recent_errors' => $this->recentErrors($rows),
            'offline_after_minutes' => (int) config('services.bot.offline_after_minutes', 3),
            'telemetry_enabled' => (string) config('services.bot.telemetry_token') !== '',
        ]);
    }

    private function status(?BotHeartbeat $latest): string
    {
        if (! $latest) {
            return 'never';
        }

        $minutes = max(1, (int) config('services.bot.offline_after_minutes', 3));

        return $latest->reported_at->gte(now()->subMinutes($minutes)) ? 'online' : 'offline';
    }

    /**
     * @param  Collection<int, BotHeartbeat>  $rows
     * @return array<string, int|float|null>
     */
    private function totals(Collection $rows): array
    {
        $apiCalls = (int) $rows->sum('api_calls');
        $hits = (int) $rows->sum('cache_hits');
        $lookups = $hits + (int) $rows->sum('cache_misses');
        $expected = $rows->isEmpty() ? 0 : (self::WINDOW_HOURS * 3600) / max(1, (int) $rows->avg('interval_seconds'));

        return [
            'commands' => (int) ($rows->sum('slash_count') + $rows->sum('prefix_count')),
            'errors' => (int) $rows->sum('error_count'),
            'api_calls' => $apiCalls,
            'api_failures' => (int) $rows->sum('api_failures'),
            'api_latency_ms' => $apiCalls > 0
                ? (int) round($rows->sum(fn (BotHeartbeat $r) => $r->api_latency_ms_avg * $r->api_calls) / $apiCalls)
                : null,
            'cache_hit_rate' => $lookups > 0 ? round($hits / $lookups * 100, 1) : null,
            // Share of expected heartbeats actually received in the window.
            'coverage_pct' => $expected > 0 ? min(100.0, round($rows->count() / $expected * 100, 1)) : null,
        ];
    }

    /**
     * One entry per hour in the window, oldest first, empty hours included.
     *
     * @param  Collection<int, BotHeartbeat>  $rows
     * @return array<int, array{hour: string, commands: int, errors: int, api_failures: int}>
     */
    private function hourly(Collection $rows): array
    {
        $buckets = [];
        for ($i = self::WINDOW_HOURS - 1; $i >= 0; $i--) {
            $hour = now()->startOfHour()->subHours($i);
            $buckets[$hour->toIso8601String()] = ['hour' => $hour->toIso8601String(), 'commands' => 0, 'errors' => 0, 'api_failures' => 0];
        }

        foreach ($rows as $row) {
            $key = $row->reported_at->copy()->startOfHour()->toIso8601String();
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['commands'] += $row->slash_count + $row->prefix_count;
            $buckets[$key]['errors'] += $row->error_count;
            $buckets[$key]['api_failures'] += $row->api_failures;
        }

        return array_values($buckets);
    }

    /**
     * @param  Collection<int, BotHeartbeat>  $rows
     * @return array<int, array{name: string, count: int}>
     */
    private function topCommands(Collection $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            foreach ($row->commands ?? [] as $name => $count) {
                $counts[$name] = ($counts[$name] ?? 0) + $count;
            }
        }
        arsort($counts);

        return collect($counts)->take(10)->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])->values()->all();
    }

    /**
     * @param  Collection<int, BotHeartbeat>  $rows
     * @return array<int, array{at: string, command: string|null, message: string}>
     */
    private function recentErrors(Collection $rows): array
    {
        $errors = [];
        foreach ($rows->reverse() as $row) {
            foreach ($row->error_samples ?? [] as $sample) {
                $errors[] = ['at' => $row->reported_at->toIso8601String(), 'command' => $sample['command'] ?? null, 'message' => $sample['message']];
                if (count($errors) >= self::RECENT_ERRORS) {
                    return $errors;
                }
            }
        }

        return $errors;
    }
}
