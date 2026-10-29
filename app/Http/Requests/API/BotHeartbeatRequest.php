<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class BotHeartbeatRequest extends FormRequest
{
    /** Authenticated by the bot.telemetry middleware (shared secret). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bot_version' => ['nullable', 'string', 'max:32'],
            'interval_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'uptime_seconds' => ['required', 'integer', 'min:0'],
            'ws_ping_ms' => ['nullable', 'integer', 'min:-1', 'max:600000'],
            'guild_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'memory_mb' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'slash_count' => ['required', 'integer', 'min:0', 'max:10000000'],
            'prefix_count' => ['required', 'integer', 'min:0', 'max:10000000'],
            'error_count' => ['required', 'integer', 'min:0', 'max:10000000'],
            'api_calls' => ['required', 'integer', 'min:0', 'max:10000000'],
            'api_failures' => ['required', 'integer', 'min:0', 'max:10000000'],
            'api_retries' => ['required', 'integer', 'min:0', 'max:10000000'],
            'api_latency_ms_avg' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'cache_hits' => ['required', 'integer', 'min:0', 'max:10000000'],
            'cache_misses' => ['required', 'integer', 'min:0', 'max:10000000'],
            'commands' => ['nullable', 'array', 'max:100'],
            'commands.*' => ['integer', 'min:0', 'max:10000000'],
            'error_samples' => ['nullable', 'array', 'max:20'],
            'error_samples.*.command' => ['nullable', 'string', 'max:50'],
            'error_samples.*.message' => ['required', 'string', 'max:300'],
        ];
    }
}
