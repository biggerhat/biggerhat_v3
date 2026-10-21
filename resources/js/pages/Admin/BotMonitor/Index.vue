<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { Bot } from 'lucide-vue-next';
import { computed } from 'vue';

interface HourBucket {
    hour: string;
    commands: number;
    errors: number;
    api_failures: number;
}

const props = defineProps<{
    status: 'never' | 'online' | 'offline';
    latest: {
        reported_at: string;
        bot_version: string | null;
        uptime_seconds: number;
        ws_ping_ms: number | null;
        guild_count: number;
        memory_mb: number | null;
    } | null;
    totals: {
        commands: number;
        errors: number;
        api_calls: number;
        api_failures: number;
        api_latency_ms: number | null;
        cache_hit_rate: number | null;
        coverage_pct: number | null;
    };
    hourly: HourBucket[];
    top_commands: { name: string; count: number }[];
    recent_errors: { at: string; command: string | null; message: string }[];
    offline_after_minutes: number;
    telemetry_enabled: boolean;
}>();

// Keep the page live without a websocket: reload just the data every 30s.
useIntervalFn(() => router.reload({ preserveScroll: true }), 30_000);

const statusLabel = computed(() => ({ online: 'Online', offline: 'Offline', never: 'No data yet' })[props.status]);
const statusClass = computed(
    () =>
        ({
            online: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
            offline: 'bg-red-500/15 text-red-700 dark:text-red-400',
            never: 'bg-muted text-muted-foreground',
        })[props.status],
);

const maxCommands = computed(() => Math.max(1, ...props.hourly.map((h) => h.commands)));
const maxTop = computed(() => Math.max(1, ...props.top_commands.map((c) => c.count)));

const formatDate = (s: string) => new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'medium' });
const formatHour = (s: string) => new Date(s).toLocaleTimeString(undefined, { hour: 'numeric' });

const formatUptime = (seconds: number) => {
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return d > 0 ? `${d}d ${h}h` : h > 0 ? `${h}h ${m}m` : `${m}m`;
};

const tiles = computed(() => [
    { label: 'Servers', value: props.latest?.guild_count ?? '—' },
    { label: 'Uptime', value: props.latest ? formatUptime(props.latest.uptime_seconds) : '—' },
    { label: 'Gateway ping', value: props.latest?.ws_ping_ms != null && props.latest.ws_ping_ms >= 0 ? `${props.latest.ws_ping_ms} ms` : '—' },
    { label: 'Memory', value: props.latest?.memory_mb != null ? `${props.latest.memory_mb} MB` : '—' },
    { label: 'Commands (24h)', value: props.totals.commands.toLocaleString() },
    { label: 'Errors (24h)', value: props.totals.errors.toLocaleString(), warn: props.totals.errors > 0 },
    {
        label: 'API failures (24h)',
        value: `${props.totals.api_failures.toLocaleString()} / ${props.totals.api_calls.toLocaleString()}`,
        warn: props.totals.api_failures > 0,
    },
    { label: 'API latency', value: props.totals.api_latency_ms != null ? `${props.totals.api_latency_ms} ms` : '—' },
    { label: 'Cache hit rate', value: props.totals.cache_hit_rate != null ? `${props.totals.cache_hit_rate}%` : '—' },
    { label: 'Heartbeat coverage', value: props.totals.coverage_pct != null ? `${props.totals.coverage_pct}%` : '—' },
]);
</script>

<template>
    <Head title="Bot Monitor - Admin" />

    <PageBanner title="Bot Monitor" class="mb-2">
        <template #logo>
            <div class="flex size-16 items-center justify-center md:size-20">
                <Bot class="size-8 text-primary md:size-10" />
            </div>
        </template>
        <template #subtitle>
            <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs md:py-2 md:text-sm">
                <Badge :class="statusClass" variant="outline">{{ statusLabel }}</Badge>
                <span v-if="latest" class="text-muted-foreground">last heartbeat {{ formatDate(latest.reported_at) }}</span>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-6 px-4 py-6 lg:px-8 xl:px-12">
        <Card v-if="!telemetry_enabled">
            <CardContent class="p-4 text-sm">
                Telemetry is disabled. Set <code class="font-mono">BOT_TELEMETRY_TOKEN</code> here and in the bot's
                <code class="font-mono">.env</code> to enable the heartbeat endpoint.
            </CardContent>
        </Card>
        <Card v-else-if="status === 'never'">
            <CardContent class="p-4 text-sm text-muted-foreground">No heartbeat received yet. Start the bot with telemetry configured.</CardContent>
        </Card>
        <Card v-else-if="status === 'offline'">
            <CardContent class="p-4 text-sm text-red-700 dark:text-red-400">
                No heartbeat for over {{ offline_after_minutes }} minutes. Check the bot process (<code class="font-mono">pm2 logs hat_golem</code>).
            </CardContent>
        </Card>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
            <Card v-for="tile in tiles" :key="tile.label">
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">{{ tile.label }}</p>
                    <p class="mt-1 text-lg font-semibold" :class="{ 'text-red-700 dark:text-red-400': tile.warn }">{{ tile.value }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="p-4">
                <h2 class="mb-3 text-sm font-semibold">Commands per hour (last 24h)</h2>
                <div class="flex h-32 items-end gap-1" role="img" aria-label="Commands per hour">
                    <div v-for="h in hourly" :key="h.hour" class="group relative flex h-full flex-1 items-end">
                        <div
                            class="w-full rounded-t"
                            :class="h.errors > 0 ? 'bg-red-500/70' : 'bg-primary/70'"
                            :style="{ height: `${Math.max(h.commands > 0 ? 4 : 0, (h.commands / maxCommands) * 100)}%` }"
                            :title="`${formatHour(h.hour)}: ${h.commands} commands, ${h.errors} errors`"
                        />
                    </div>
                </div>
                <div class="mt-1 flex justify-between text-[10px] text-muted-foreground">
                    <span>{{ formatHour(hourly[0].hour) }}</span>
                    <span>{{ formatHour(hourly[hourly.length - 1].hour) }}</span>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Red bars are hours with at least one error.</p>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardContent class="p-4">
                    <h2 class="mb-3 text-sm font-semibold">Top commands (24h)</h2>
                    <p v-if="top_commands.length === 0" class="text-sm text-muted-foreground">No commands run yet.</p>
                    <ul v-else class="space-y-2">
                        <li v-for="c in top_commands" :key="c.name" class="text-sm">
                            <div class="flex justify-between">
                                <span class="font-mono">{{ c.name }}</span>
                                <span class="text-muted-foreground">{{ c.count.toLocaleString() }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded bg-muted">
                                <div class="h-full rounded bg-primary/70" :style="{ width: `${(c.count / maxTop) * 100}%` }" />
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <h2 class="mb-3 text-sm font-semibold">Recent errors</h2>
                    <p v-if="recent_errors.length === 0" class="text-sm text-muted-foreground">No errors in the last 24 hours. 🎉</p>
                    <ul v-else class="space-y-2">
                        <li v-for="(e, i) in recent_errors" :key="i" class="text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-muted-foreground">{{ formatDate(e.at) }}</span>
                                <Badge v-if="e.command" variant="outline" class="font-mono text-[10px]">{{ e.command }}</Badge>
                            </div>
                            <p class="mt-0.5 break-words font-mono text-red-700 dark:text-red-400">{{ e.message }}</p>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
