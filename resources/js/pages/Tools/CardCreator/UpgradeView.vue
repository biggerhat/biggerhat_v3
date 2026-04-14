<script setup lang="ts">
import UpgradeCardRenderer from '@/components/CardCreator/UpgradeCardRenderer.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    upgrade: {
        id: number;
        name: string;
        display_name: string;
        domain: string;
        type: string | null;
        faction: string | null;
        limitations: string | null;
        plentiful: number | null;
        master_name: string | null;
        keyword_name: string | null;
        content_blocks: { type: string; text?: string; data?: Record<string, unknown> }[] | null;
        back_tokens: { name: string; description: string | null }[] | null;
        back_markers: { name: string; description: string | null }[] | null;
    };
    creator_name: string;
}>();

const domainLabel = props.upgrade.domain === 'crew' ? 'Crew Card' : 'Upgrade';

const upgradeTypeLabel = (() => {
    if (!props.upgrade.type) return null;
    return props.upgrade.type
        .split('_')
        .map((w: string) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
})();

const limitationsLabel = (() => {
    if (!props.upgrade.limitations) return null;
    return props.upgrade.limitations
        .split('_')
        .map((w: string) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
})();

const blockTypeLabel = (type: string) => {
    const map: Record<string, string> = { text: 'Text', ability: 'Ability', action: 'Action', trigger: 'Trigger' };
    return map[type] ?? type;
};
</script>

<template>
    <Head :title="`${upgrade.display_name} — Custom ${domainLabel}`" />

    <div class="relative pb-12">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]" :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }" />

        <PageBanner :title="upgrade.display_name">
            <template #subtitle>
                <div class="flex items-center gap-2 px-2 text-sm text-muted-foreground">
                    <Badge class="bg-purple-600 text-white">Custom {{ domainLabel }}</Badge>
                    <span>Created by {{ creator_name }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 max-w-3xl px-4 lg:px-6">
            <div class="grid gap-6 md:grid-cols-2">
                <UpgradeCardRenderer
                    :name="upgrade.name"
                    :domain="upgrade.domain"
                    :faction="upgrade.faction"
                    :upgrade-type="upgrade.type"
                    :upgrade-type-label="upgradeTypeLabel"
                    :limitations="upgrade.limitations"
                    :limitations-label="limitationsLabel"
                    :master-name="upgrade.master_name"
                    :keyword-name="upgrade.keyword_name"
                    :content-blocks="upgrade.content_blocks ?? []"
                    :back-tokens="upgrade.back_tokens ?? []"
                    :back-markers="upgrade.back_markers ?? []"
                />

                <div class="space-y-4">
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <div class="flex items-center gap-2">
                                <FactionLogo v-if="upgrade.faction" :faction="upgrade.faction" class-name="size-5" />
                                <span class="font-semibold">{{ upgrade.display_name }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ domainLabel }}
                                <span v-if="upgrade.master_name"> | {{ upgrade.master_name }}</span>
                            </div>
                            <div v-if="upgradeTypeLabel" class="text-xs text-muted-foreground">Type: {{ upgradeTypeLabel }}</div>
                            <div v-if="limitationsLabel" class="text-xs text-muted-foreground">Limitations: {{ limitationsLabel }}</div>
                        </CardContent>
                    </Card>

                    <Card v-if="upgrade.content_blocks?.length">
                        <CardContent class="space-y-2 p-4">
                            <h3 class="text-xs font-semibold">Card Content</h3>
                            <div v-for="(block, idx) in upgrade.content_blocks" :key="idx" class="rounded border p-2 text-xs">
                                <div v-if="block.type === 'text'" class="italic text-muted-foreground">{{ block.text }}</div>
                                <div v-else>
                                    <Badge variant="outline" class="mb-1 text-[8px]">{{ blockTypeLabel(block.type) }}</Badge>
                                    <div class="font-medium">{{ block.data?.name }}</div>
                                    <div v-if="block.data?.description" class="mt-0.5 text-muted-foreground">{{ block.data.description }}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
