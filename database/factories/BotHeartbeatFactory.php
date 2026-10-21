<?php

namespace Database\Factories;

use App\Models\BotHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotHeartbeat>
 */
class BotHeartbeatFactory extends Factory
{
    protected $model = BotHeartbeat::class;

    public function definition(): array
    {
        return [
            'reported_at' => now(),
            'bot_version' => '2.0.0',
            'interval_seconds' => 60,
            'uptime_seconds' => 3600,
            'ws_ping_ms' => 42,
            'guild_count' => 12,
            'memory_mb' => 90,
            'slash_count' => 3,
            'prefix_count' => 1,
            'error_count' => 0,
            'api_calls' => 4,
            'api_failures' => 0,
            'api_retries' => 0,
            'api_latency_ms_avg' => 120,
            'cache_hits' => 2,
            'cache_misses' => 2,
            'commands' => ['mini' => 3, 'crew' => 1],
            'error_samples' => [],
        ];
    }

    public function at(\DateTimeInterface $when): static
    {
        return $this->state(['reported_at' => $when]);
    }

    public function failing(): static
    {
        return $this->state([
            'error_count' => 2,
            'api_failures' => 2,
            'error_samples' => [['command' => 'mini', 'message' => 'API error: 500']],
        ]);
    }
}
