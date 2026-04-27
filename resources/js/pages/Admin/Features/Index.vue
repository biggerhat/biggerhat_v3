<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Head, router } from '@inertiajs/vue3';
import { Flag } from 'lucide-vue-next';

interface Flag {
    name: string;
    label: string;
    description: string;
    default: boolean;
    active: boolean;
    has_override: boolean;
}

defineProps<{
    flags: Flag[];
    storage_ready?: boolean;
}>();

const apply = (flag: Flag, nextActive: boolean) => {
    router.post(
        route('admin.features.update', flag.name),
        { action: nextActive ? 'activate' : 'deactivate' },
        { preserveScroll: true },
    );
};

const clearOverride = (flag: Flag) => {
    router.post(route('admin.features.update', flag.name), { action: 'clear' }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Feature Flags - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <Flag class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Feature Flags</h1>
        </div>
        <p class="text-sm text-muted-foreground">
            Site-wide overrides for flags registered in <code class="rounded bg-muted px-1 text-xs">FeatureFlagsServiceProvider</code>. Toggle active to
            activate the flag for everyone; clear the override to fall back to the registry default.
        </p>

        <div
            v-if="storage_ready === false"
            class="rounded-md border border-amber-500/40 bg-amber-500/5 px-4 py-2 text-xs text-amber-900 dark:text-amber-200"
        >
            The Pennant <code class="rounded bg-muted px-1">features</code> table doesn't exist yet — flags are read-only until you run
            <code class="rounded bg-muted px-1">php artisan migrate</code>.
        </div>

        <div class="space-y-2">
            <Card v-for="flag in flags" :key="flag.name">
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold">{{ flag.label }}</span>
                            <code class="rounded bg-muted px-1.5 py-0.5 text-[11px]">{{ flag.name }}</code>
                            <Badge v-if="flag.has_override" variant="outline" class="text-[10px]">override</Badge>
                            <Badge v-else variant="secondary" class="text-[10px]">default ({{ flag.default ? 'on' : 'off' }})</Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ flag.description }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <Switch :model-value="flag.active" @update:model-value="apply(flag, $event)" />
                        <Button v-if="flag.has_override" variant="ghost" size="sm" @click="clearOverride(flag)">Clear override</Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
