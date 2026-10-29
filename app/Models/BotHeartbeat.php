<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One telemetry snapshot pushed by the Discord bot (hat_golem). Counter
 * columns are per-interval deltas.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $reported_at
 * @property string|null $bot_version
 * @property int $interval_seconds
 * @property int $uptime_seconds
 * @property int|null $ws_ping_ms
 * @property int $guild_count
 * @property int|null $memory_mb
 * @property int $slash_count
 * @property int $prefix_count
 * @property int $error_count
 * @property int $api_calls
 * @property int $api_failures
 * @property int $api_retries
 * @property int|null $api_latency_ms_avg
 * @property int $cache_hits
 * @property int $cache_misses
 * @property array<string, int>|null $commands
 * @property array<int, array{command: string|null, message: string}>|null $error_samples
 */
class BotHeartbeat extends Model
{
    /** @use HasFactory<\Database\Factories\BotHeartbeatFactory> */
    use HasFactory;

    use Prunable;

    public const RETENTION_DAYS = 14;

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return Builder<BotHeartbeat> */
    public function prunable(): Builder
    {
        return static::where('reported_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'commands' => 'array',
            'error_samples' => 'array',
        ];
    }
}
