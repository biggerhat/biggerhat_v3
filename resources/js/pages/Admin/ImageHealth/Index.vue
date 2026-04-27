<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, router } from '@inertiajs/vue3';
import { ImageOff, ScanSearch } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Missing {
    table: string;
    column: string;
    id: number;
    path: string;
}

interface Report {
    scanned_at: string;
    checked_count: number;
    broken_count: number;
    missing: Missing[];
    skipped_columns?: string[];
}

const props = defineProps<{ report: Report | null }>();

const scanning = ref(false);

const runScan = () => {
    scanning.value = true;
    router.post(
        route('admin.image_health.scan'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                scanning.value = false;
            },
        },
    );
};

const formatDate = (s: string) => new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });

// Group broken records by table for a tidy report.
const grouped = computed(() => {
    if (!props.report?.missing) return [];
    const map = new Map<string, Missing[]>();
    for (const row of props.report.missing) {
        if (!map.has(row.table)) map.set(row.table, []);
        map.get(row.table)!.push(row);
    }
    return Array.from(map.entries()).map(([table, rows]) => ({ table, rows }));
});
</script>

<template>
    <Head title="Image Health - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <ImageOff class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Image Health</h1>
        </div>
        <p class="text-sm text-muted-foreground">
            Scans every image-bearing column across the schema and flags rows whose stored path doesn't resolve to a file on disk. Cached for 60 minutes
            after each scan.
        </p>

        <Card>
            <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div v-if="report" class="text-sm">
                    <div>
                        Last scan: <span class="font-medium">{{ formatDate(report.scanned_at) }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-muted-foreground">
                        Checked {{ report.checked_count }} paths · {{ report.broken_count }} broken
                    </div>
                </div>
                <div v-else class="text-sm text-muted-foreground">No scan yet.</div>
                <Button :disabled="scanning" @click="runScan">
                    <ScanSearch class="mr-1.5 size-3.5" />
                    {{ scanning ? 'Scanning…' : 'Run scan' }}
                </Button>
            </CardContent>
        </Card>

        <div
            v-if="report?.skipped_columns?.length"
            class="rounded-md border border-amber-500/40 bg-amber-500/5 px-4 py-2 text-xs text-amber-900 dark:text-amber-200"
        >
            Skipped {{ report.skipped_columns.length }} column(s) that no longer exist in the schema (likely dropped by a later migration). Update the
            <code class="rounded bg-muted px-1">TARGETS</code> list in <code class="rounded bg-muted px-1">ImageHealthAdminController</code> to clean
            this up: {{ report.skipped_columns.join(', ') }}
        </div>

        <div v-if="report && report.broken_count === 0" class="py-12 text-center text-sm text-muted-foreground">
            All image paths resolve. 🎉
        </div>

        <div v-if="grouped.length" class="space-y-3">
            <Card v-for="group in grouped" :key="group.table">
                <CardContent class="space-y-2 p-4">
                    <div class="flex items-center gap-2">
                        <code class="rounded bg-muted px-1.5 py-0.5 text-sm font-semibold">{{ group.table }}</code>
                        <Badge variant="destructive">{{ group.rows.length }} broken</Badge>
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="row in group.rows"
                            :key="`${row.table}:${row.id}:${row.column}`"
                            class="flex flex-wrap items-center gap-2 rounded-md border px-2 py-1.5 text-xs"
                        >
                            <Badge variant="outline" class="text-[10px]">id #{{ row.id }}</Badge>
                            <Badge variant="secondary" class="text-[10px]">{{ row.column }}</Badge>
                            <code class="min-w-0 flex-1 truncate font-mono">{{ row.path }}</code>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
