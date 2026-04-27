<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router } from '@inertiajs/vue3';
import { Activity as ActivityIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Causer {
    id: number;
    name: string;
}

interface ActivityRow {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | null;
    causer: Causer | null;
    properties: { attributes?: Record<string, unknown>; old?: Record<string, unknown> } | null;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    activities: Paginated<ActivityRow>;
    filters: { log: string | null; event: string | null; causer: number | null };
    log_names: string[];
    events: string[];
}>();

const logFilter = ref(props.filters.log ?? '__all__');
const eventFilter = ref(props.filters.event ?? '__all__');

const applyFilters = () => {
    router.get(
        route('admin.activity.index'),
        {
            log: logFilter.value === '__all__' ? null : logFilter.value,
            event: eventFilter.value === '__all__' ? null : eventFilter.value,
            causer: props.filters.causer,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const formatDate = (s: string) =>
    new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });

const subjectShortType = (t: string | null) => (t ? t.replace(/^.*\\/, '').replace(/\\Models\\?/, '') : '—');

const eventBadgeClass = (event: string | null) => {
    switch (event) {
        case 'created':
            return 'border-green-500/40 bg-green-500/10 text-green-700 dark:text-green-400';
        case 'updated':
            return 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400';
        case 'deleted':
            return 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-400';
        default:
            return 'border-muted text-muted-foreground';
    }
};

// Render the dirty-attribute diff as a compact key=oldVal→newVal list.
const diffSummary = (row: ActivityRow): string[] => {
    const next = row.properties?.attributes ?? {};
    const prev = row.properties?.old ?? {};
    const keys = Object.keys(next);
    if (keys.length === 0) return [];
    return keys.slice(0, 8).map((k) => {
        const before = JSON.stringify(prev[k] ?? null);
        const after = JSON.stringify(next[k]);
        return row.event === 'created' ? `${k}=${after}` : `${k}: ${before} → ${after}`;
    });
};

const hasFilters = computed(
    () => logFilter.value !== '__all__' || eventFilter.value !== '__all__' || !!props.filters.causer,
);

const clearFilters = () => {
    logFilter.value = '__all__';
    eventFilter.value = '__all__';
    router.get(route('admin.activity.index'), {}, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Activity Log - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <ActivityIcon class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Activity Log</h1>
            <Badge variant="secondary" class="ml-2">{{ activities.total }} total</Badge>
        </div>
        <p class="text-sm text-muted-foreground">Audit trail of admin-initiated changes across content models.</p>

        <Card>
            <CardContent class="flex flex-wrap items-end gap-3 p-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase text-muted-foreground">Model</label>
                    <Select v-model="logFilter" @update:model-value="applyFilters">
                        <SelectTrigger class="w-44"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">All models</SelectItem>
                            <SelectItem v-for="name in log_names" :key="name" :value="name">{{ name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase text-muted-foreground">Event</label>
                    <Select v-model="eventFilter" @update:model-value="applyFilters">
                        <SelectTrigger class="w-36"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">All events</SelectItem>
                            <SelectItem v-for="ev in events" :key="ev" :value="ev">{{ ev }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters">Clear</Button>
            </CardContent>
        </Card>

        <div class="space-y-2">
            <Card v-for="row in activities.data" :key="row.id">
                <CardContent class="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:gap-4">
                    <div class="flex shrink-0 flex-col gap-1 text-xs text-muted-foreground sm:w-44">
                        <span>{{ formatDate(row.created_at) }}</span>
                        <span v-if="row.causer">by <span class="font-medium text-foreground">{{ row.causer.name }}</span></span>
                        <span v-else>by system</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" :class="eventBadgeClass(row.event)">{{ row.event ?? 'event' }}</Badge>
                            <Badge variant="secondary">{{ row.log_name ?? 'default' }}</Badge>
                            <span class="text-sm font-medium">
                                {{ subjectShortType(row.subject_type) }}<span v-if="row.subject_id" class="text-muted-foreground"> #{{ row.subject_id }}</span>
                            </span>
                        </div>
                        <p v-if="row.description && row.description !== row.event" class="mt-1 text-sm">{{ row.description }}</p>
                        <ul v-if="diffSummary(row).length" class="mt-2 space-y-0.5 font-mono text-xs text-muted-foreground">
                            <li v-for="(line, idx) in diffSummary(row)" :key="idx" class="truncate">{{ line }}</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
            <div v-if="!activities.data.length" class="py-12 text-center text-sm text-muted-foreground">No activity matches your filters.</div>
        </div>

        <div v-if="activities.last_page > 1" class="flex flex-wrap gap-1">
            <Button
                v-for="link in activities.links"
                :key="link.label"
                size="sm"
                :variant="link.active ? 'default' : 'outline'"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url, { preserveScroll: true })"
            >
                <span v-html="link.label" />
            </Button>
        </div>
    </div>
</template>
