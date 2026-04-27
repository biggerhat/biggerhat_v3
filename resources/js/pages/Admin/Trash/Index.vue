<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { RotateCcw, Trash2 } from 'lucide-vue-next';

const confirm = useConfirm();

interface Tab {
    key: string;
    label: string;
    count: number;
}

interface Row {
    id: number;
    name: string;
    deleted_at: string | null;
}

const props = defineProps<{
    kind: string;
    rows: Row[];
    tabs: Tab[];
}>();

const switchTab = (kind: string) => {
    router.get(route('admin.trash.index'), { kind }, { preserveScroll: true, preserveState: true, replace: true });
};

const restore = async (row: Row) => {
    if (!(await confirm({ title: `Restore "${row.name}"?`, message: 'It will return to the public site.', confirmLabel: 'Restore' }))) return;
    router.post(route('admin.trash.restore', { kind: props.kind, id: row.id }), {}, { preserveScroll: true });
};

const forceDelete = async (row: Row) => {
    if (!(await confirm({
        title: `Permanently delete "${row.name}"?`,
        message: 'This cannot be undone.',
        confirmLabel: 'Delete forever',
        destructive: true,
    }))) return;
    router.post(route('admin.trash.force_delete', { kind: props.kind, id: row.id }), {}, { preserveScroll: true });
};

const formatDate = (s: string | null) => (s ? new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '—');
</script>

<template>
    <Head title="Trash - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <Trash2 class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Trash</h1>
        </div>
        <p class="text-sm text-muted-foreground">
            Soft-deleted records across the schema. Restore brings them back to the public site; force-delete removes them permanently.
        </p>

        <div class="flex flex-wrap gap-1.5">
            <Button
                v-for="tab in tabs"
                :key="tab.key"
                size="sm"
                :variant="tab.key === kind ? 'default' : 'outline'"
                @click="switchTab(tab.key)"
            >
                {{ tab.label }}
                <Badge variant="secondary" class="ml-1.5 text-[10px]">{{ tab.count }}</Badge>
            </Button>
        </div>

        <div class="space-y-2">
            <Card v-for="row in rows" :key="`${kind}:${row.id}`">
                <CardContent class="flex items-center gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ row.name }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">deleted {{ formatDate(row.deleted_at) }} · #{{ row.id }}</div>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <Button variant="outline" size="sm" @click="restore(row)" title="Restore">
                            <RotateCcw class="size-3.5" />
                        </Button>
                        <Button variant="destructive" size="sm" @click="forceDelete(row)" title="Force delete">
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
            <div v-if="!rows.length" class="py-12 text-center text-sm text-muted-foreground">Nothing in trash for this model.</div>
        </div>
    </div>
</template>
