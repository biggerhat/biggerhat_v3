<?php

use App\Console\Commands\CheckBotHeartbeat;
use App\Models\BotHeartbeat;
use App\Models\User;
use App\Notifications\BotStatusChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['services.bot.telemetry_token' => 'secret-token', 'services.bot.offline_after_minutes' => 3, 'services.bot.alert_webhook' => null]);
    Cache::forget(CheckBotHeartbeat::OFFLINE_FLAG);

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
});

function heartbeatPayload(array $overrides = []): array
{
    return array_merge([
        'bot_version' => '2.0.0',
        'interval_seconds' => 60,
        'uptime_seconds' => 120,
        'ws_ping_ms' => 55,
        'guild_count' => 9,
        'memory_mb' => 80,
        'slash_count' => 2,
        'prefix_count' => 1,
        'error_count' => 1,
        'api_calls' => 3,
        'api_failures' => 1,
        'api_retries' => 1,
        'api_latency_ms_avg' => 150,
        'cache_hits' => 1,
        'cache_misses' => 2,
        'commands' => ['mini' => 2, 'crew' => 1],
        'error_samples' => [['command' => 'mini', 'message' => 'API error: 500']],
    ], $overrides);
}

// ─── Telemetry endpoint ───

it('stores a heartbeat for the bot', function () {
    $this->withToken('secret-token')->postJson(route('api.bot.heartbeat'), heartbeatPayload())->assertOk();

    $beat = BotHeartbeat::first();
    expect($beat->guild_count)->toBe(9)
        ->and($beat->commands)->toBe(['mini' => 2, 'crew' => 1])
        ->and($beat->reported_at->isToday())->toBeTrue();
});

it('rejects a missing or wrong token', function () {
    $this->postJson(route('api.bot.heartbeat'), heartbeatPayload())->assertUnauthorized();
    $this->withToken('nope')->postJson(route('api.bot.heartbeat'), heartbeatPayload())->assertUnauthorized();
    expect(BotHeartbeat::count())->toBe(0);
});

it('is disabled (404) when no secret is configured', function () {
    config(['services.bot.telemetry_token' => null]);

    $this->withToken('anything')->postJson(route('api.bot.heartbeat'), heartbeatPayload())->assertNotFound();
});

it('rejects malformed heartbeats', function (array $override, string $field) {
    $this->withToken('secret-token')
        ->postJson(route('api.bot.heartbeat'), heartbeatPayload($override))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing guild count' => [['guild_count' => null], 'guild_count'],
    'negative counter' => [['error_count' => -1], 'error_count'],
    'zero interval' => [['interval_seconds' => 0], 'interval_seconds'],
    'non-integer command count' => [['commands' => ['mini' => 'x']], 'commands.mini'],
    'too many error samples' => [['error_samples' => array_fill(0, 21, ['message' => 'x'])], 'error_samples'],
    'error sample without message' => [['error_samples' => [['command' => 'mini']]], 'error_samples.0.message'],
    'overlong error message' => [['error_samples' => [['message' => str_repeat('x', 301)]]], 'error_samples.0.message'],
]);

// ─── Admin page ───

it('is super-admin only', function () {
    $this->get(route('admin.bot_monitor.index'))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('admin.bot_monitor.index'))->assertForbidden();
});

it('shows no data before the first heartbeat', function () {
    $this->actingAs($this->superAdmin)->get(route('admin.bot_monitor.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/BotMonitor/Index')->where('status', 'never')->where('latest', null)->has('hourly', 24));
});

it('reports online, offline and aggregates the last day', function () {
    BotHeartbeat::factory()->at(now()->subMinutes(30))->failing()->create(['commands' => ['mini' => 5]]);
    BotHeartbeat::factory()->at(now()->subMinute())->create(['commands' => ['mini' => 1, 'crew' => 4]]);
    BotHeartbeat::factory()->at(now()->subDays(3))->create(['commands' => ['old' => 99]]);

    $this->actingAs($this->superAdmin)->get(route('admin.bot_monitor.index'))
        ->assertInertia(fn ($page) => $page
            ->where('status', 'online')
            ->where('totals.errors', 2)
            ->where('totals.commands', 8)
            ->where('top_commands.0', ['name' => 'mini', 'count' => 6])
            ->where('top_commands.1', ['name' => 'crew', 'count' => 4])
            ->where('recent_errors.0.message', 'API error: 500'));

    $this->travel(10)->minutes();

    $this->actingAs($this->superAdmin)->get(route('admin.bot_monitor.index'))
        ->assertInertia(fn ($page) => $page->where('status', 'offline'));
});

// ─── Alerts ───

it('alerts once when heartbeats stop and once on recovery', function () {
    Notification::fake();
    BotHeartbeat::factory()->at(now()->subMinutes(10))->create();

    $this->artisan('bot:check-heartbeat')->assertSuccessful();
    $this->artisan('bot:check-heartbeat')->assertSuccessful();
    Notification::assertSentToTimes($this->superAdmin, BotStatusChanged::class, 1);
    Notification::assertSentTo($this->superAdmin, BotStatusChanged::class, fn ($n) => $n->online === false);

    BotHeartbeat::factory()->at(now())->create();
    $this->artisan('bot:check-heartbeat')->assertSuccessful();
    $this->artisan('bot:check-heartbeat')->assertSuccessful();
    Notification::assertSentToTimes($this->superAdmin, BotStatusChanged::class, 2);
    Notification::assertSentTo($this->superAdmin, BotStatusChanged::class, fn ($n) => $n->online === true);
});

it('stays quiet when the bot has never reported or is healthy', function () {
    Notification::fake();

    $this->artisan('bot:check-heartbeat')->assertSuccessful();
    BotHeartbeat::factory()->create();
    $this->artisan('bot:check-heartbeat')->assertSuccessful();

    Notification::assertNothingSent();
});

it('also posts to the alert webhook when configured', function () {
    Notification::fake();
    Http::fake();
    config(['services.bot.alert_webhook' => 'https://discord.test/hook']);
    BotHeartbeat::factory()->at(now()->subMinutes(10))->create();

    $this->artisan('bot:check-heartbeat')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/hook' && str_contains($request['content'], 'stopped reporting'));
});

it('prunes heartbeats older than the retention window', function () {
    BotHeartbeat::factory()->at(now()->subDays(BotHeartbeat::RETENTION_DAYS + 1))->create();
    $keep = BotHeartbeat::factory()->at(now()->subDay())->create();

    $this->artisan('model:prune', ['--model' => [BotHeartbeat::class]])->assertSuccessful();

    expect(BotHeartbeat::pluck('id')->all())->toBe([$keep->id]);
});
