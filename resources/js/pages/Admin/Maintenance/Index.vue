<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { CircleSlash, PowerOff, ServerCrash } from 'lucide-vue-next';
import { ref } from 'vue';

const confirm = useConfirm();

const props = defineProps<{
    is_down: boolean;
    down_payload: Record<string, unknown> | null;
    bypass_url: string | null;
}>();

const copied = ref(false);

const goDown = async () => {
    if (
        !(await confirm({
            title: 'Enable maintenance mode',
            message: 'Visitors will see a 503 page until you bring the site back up.',
            confirmLabel: 'Take site down',
            destructive: true,
        }))
    )
        return;
    router.post(route('admin.maintenance.down'), {}, { preserveScroll: false });
};

const goUp = async () => {
    if (!(await confirm({ title: 'Bring site back up?', message: 'Visitors will be served normally again.', confirmLabel: 'Bring up' }))) return;
    router.post(route('admin.maintenance.up'), {}, { preserveScroll: false });
};

const copyBypass = async () => {
    if (!props.bypass_url) return;
    await navigator.clipboard.writeText(props.bypass_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};
</script>

<template>
    <Head title="Maintenance Mode - Admin" />

    <PageBanner title="Maintenance Mode" class="mb-2">
        <template #logo>
            <div class="flex size-16 items-center justify-center md:size-20">
                <ServerCrash class="size-8 text-primary md:size-10" />
            </div>
        </template>
        <template #subtitle>
            <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Lock the public site behind a 503 page during a deploy or DB migration. The bypass URL keeps you (and anyone you share it with)
                in.
                <Badge :variant="is_down ? 'destructive' : 'secondary'">{{ is_down ? 'Down' : 'Up' }}</Badge>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">

        <Card>
            <CardContent class="space-y-3 p-4">
                <div v-if="is_down" class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-semibold text-destructive">
                        <PowerOff class="size-4" /> Site is in maintenance mode.
                    </div>
                    <div v-if="bypass_url" class="space-y-1">
                        <div class="text-xs text-muted-foreground">Bypass URL — open once, then you're free to browse:</div>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 truncate rounded bg-muted px-2 py-1 font-mono text-xs">{{ bypass_url }}</code>
                            <Button size="sm" variant="outline" @click="copyBypass">{{ copied ? 'Copied' : 'Copy' }}</Button>
                        </div>
                    </div>
                    <Button @click="goUp">Bring the site back up</Button>
                </div>
                <div v-else class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <CircleSlash class="size-4 text-muted-foreground" /> Site is live.
                    </div>
                    <Button variant="destructive" @click="goDown">Enable maintenance mode</Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
