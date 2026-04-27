<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, RefreshCw, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const confirm = useConfirm();

interface FailedJob {
    id: number;
    uuid: string;
    connection: string;
    queue: string;
    job_name: string;
    exception_summary: { class: string | null; message: string | null };
    exception: string;
    failed_at: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

defineProps<{
    jobs: Paginated<FailedJob>;
}>();

const expanded = ref<string | null>(null);

const toggle = (uuid: string) => {
    expanded.value = expanded.value === uuid ? null : uuid;
};

const retry = (uuid: string) => {
    router.post(route('admin.failed_jobs.retry', uuid), {}, { preserveScroll: true });
};

const destroy = async (uuid: string) => {
    if (!(await confirm({
        title: 'Delete failed job',
        message: 'Delete this failed job? This is permanent.',
        confirmLabel: 'Delete',
        destructive: true,
    }))) return;
    router.post(route('admin.failed_jobs.delete', uuid), {}, { preserveScroll: true });
};

const retryAll = async () => {
    if (!(await confirm({ title: 'Retry all jobs', message: 'Retry every failed job?', confirmLabel: 'Retry all' }))) return;
    router.post(route('admin.failed_jobs.retry_all'), {}, { preserveScroll: true });
};

const flush = async () => {
    if (!(await confirm({
        title: 'Flush failed jobs',
        message: 'Permanently delete all failed jobs?',
        confirmLabel: 'Flush all',
        destructive: true,
    }))) return;
    router.post(route('admin.failed_jobs.flush'), {}, { preserveScroll: true });
};

const formatDate = (s: string) => new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'medium' });
</script>

<template>
    <Head title="Failed Jobs - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex flex-wrap items-center gap-2">
            <AlertTriangle class="size-5 text-amber-500" />
            <h1 class="text-2xl font-semibold tracking-tight">Failed Jobs</h1>
            <Badge variant="secondary" class="ml-1">{{ jobs.total }}</Badge>
            <div class="ml-auto flex gap-2">
                <Button v-if="jobs.total > 0" variant="outline" size="sm" @click="retryAll">
                    <RefreshCw class="mr-1.5 size-3.5" /> Retry all
                </Button>
                <Button v-if="jobs.total > 0" variant="destructive" size="sm" @click="flush">
                    <Trash2 class="mr-1.5 size-3.5" /> Flush all
                </Button>
            </div>
        </div>
        <p class="text-sm text-muted-foreground">Jobs that hit the failed_jobs table. Inspect the exception, retry, or discard.</p>

        <div v-if="jobs.data.length === 0" class="py-16 text-center text-sm text-muted-foreground">No failed jobs. 🎉</div>

        <div v-else class="space-y-2">
            <Card v-for="job in jobs.data" :key="job.uuid">
                <CardContent class="p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-sm font-semibold">{{ job.job_name }}</span>
                                <Badge variant="outline" class="text-[10px]">{{ job.connection }} · {{ job.queue }}</Badge>
                                <span class="text-xs text-muted-foreground">{{ formatDate(job.failed_at) }}</span>
                            </div>
                            <p v-if="job.exception_summary.class" class="mt-1 truncate font-mono text-xs">
                                <span class="text-red-700 dark:text-red-400">{{ job.exception_summary.class }}</span
                                ><span v-if="job.exception_summary.message" class="text-muted-foreground">: {{ job.exception_summary.message }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1.5">
                            <Button variant="outline" size="sm" @click="toggle(job.uuid)">
                                {{ expanded === job.uuid ? 'Hide trace' : 'Show trace' }}
                            </Button>
                            <Button variant="outline" size="sm" @click="retry(job.uuid)">
                                <RefreshCw class="size-3.5" />
                            </Button>
                            <Button variant="outline" size="sm" @click="destroy(job.uuid)">
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                    <pre
                        v-if="expanded === job.uuid"
                        class="mt-3 max-h-96 overflow-auto rounded-md bg-muted/50 p-3 text-[11px] leading-relaxed"
                        >{{ job.exception }}</pre>
                </CardContent>
            </Card>
        </div>

        <div v-if="jobs.last_page > 1" class="flex flex-wrap gap-1">
            <Button
                v-for="link in jobs.links"
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
