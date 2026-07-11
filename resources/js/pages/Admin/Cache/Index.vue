<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { Eraser } from 'lucide-vue-next';

const confirm = useConfirm();

interface CommandRow {
    key: string;
    command: string;
    label: string;
    description: string;
}

defineProps<{ commands: CommandRow[] }>();

const clear = async (cmd: CommandRow) => {
    if (
        cmd.key === 'optimize' &&
        !(await confirm({
            title: 'Clear every cache?',
            message: 'Site may briefly slow down while caches warm again.',
            confirmLabel: 'Clear all',
        }))
    ) {
        return;
    }
    router.post(route('admin.cache.clear', cmd.key), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Cache Controls - Admin" />

    <PageBanner title="Cache Controls" class="mb-2">
        <template #logo>
            <div class="flex size-16 items-center justify-center md:size-20">
                <Eraser class="size-8 text-primary md:size-10" />
            </div>
        </template>
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Each button calls the matching <code class="rounded bg-muted px-1 text-xs">artisan *:clear</code> command. Safe to run on a live
                site.
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">

        <div class="grid gap-3 sm:grid-cols-2">
            <Card v-for="cmd in commands" :key="cmd.key">
                <CardContent class="flex items-start gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold">{{ cmd.label }}</span>
                            <Badge variant="secondary" class="font-mono text-[10px]">{{ cmd.command }}</Badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ cmd.description }}</p>
                    </div>
                    <Button variant="outline" size="sm" @click="clear(cmd)">Clear</Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
