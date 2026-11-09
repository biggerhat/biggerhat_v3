<script setup lang="ts">
import UpgradeCardRenderer from '@/components/CardCreator/UpgradeCardRenderer.vue';
import CopyToMyCards from '@/components/CardCreator/CopyToMyCards.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCardImageEvents } from '@/composables/useCardImageEvents';
import { cacheBustedImagePath } from '@/lib/cacheBustedImage';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Download, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    share_code: string;
    card_image_pending?: boolean;
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
        combination_image?: string | null;
        front_image?: string | null;
        card_image_generated_at?: string | null;
    };
    creator_name: string;
}>();

// The owner viewing their card sees the download button appear when the render finishes.
useCardImageEvents(usePage<SharedData>().props.auth.user?.id, (event) => {
    if (event.kind === 'custom_upgrade' && event.id === props.upgrade.id) router.reload({ only: ['upgrade'], preserveScroll: true });
});

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
const signedIn = !!usePage<SharedData>().props.auth.user;
</script>

<template>
    <SeoHead
        :title="`${upgrade.display_name} — Custom ${domainLabel}`"
        :description="`${upgrade.display_name}, a custom ${domainLabel.toLowerCase()} card by ${creator_name}.`"
        :image="upgrade.combination_image ?? upgrade.front_image"
    />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="upgrade.display_name">
            <template #subtitle>
                <div class="flex items-center gap-2 px-2 text-sm text-muted-foreground">
                    <Badge class="bg-purple-600 text-white">Custom {{ domainLabel }}</Badge>
                    <span>Created by {{ creator_name }}</span>
                </div>
            </template>
            <template v-if="signedIn || upgrade.combination_image || card_image_pending" #actions>
                <CopyToMyCards v-if="signedIn" :url="route('tools.card_creator.upgrades.copy_shared', share_code)" />
                <span
                    v-if="!upgrade.combination_image && card_image_pending"
                    class="mr-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                    role="status"
                >
                    <Loader2 class="size-4 animate-spin" /> Preparing card image…
                </span>
                <a
                    v-else-if="upgrade.combination_image"
                    :href="'/storage/' + cacheBustedImagePath(upgrade.combination_image, upgrade.card_image_generated_at)"
                    :download="`${upgrade.display_name}.png`"
                    class="mr-2 inline-flex h-9 items-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium transition-colors hover:bg-accent [@media(pointer:coarse)]:h-11"
                >
                    <Download class="size-4" /> Download card
                </a>
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
                                    <Badge variant="outline" class="mb-1 text-[10px]">{{ blockTypeLabel(block.type) }}</Badge>
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
