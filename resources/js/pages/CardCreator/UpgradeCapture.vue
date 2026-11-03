<script setup lang="ts">
import UpgradeBackFace from '@/components/CardCreator/UpgradeBackFace.vue';
import UpgradeFrontFace from '@/components/CardCreator/UpgradeFrontFace.vue';
import { tarotCardSize } from '@/components/CardCreator/utils';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

// Headless-capture-only page (no sidebar/header chrome) — the target of CustomUpgradeCardImageGenerator, which
// screenshots #card-front and #card-back individually. Mirrors CardCreator/Capture.vue for characters.
defineOptions({ layout: null });

interface ContentBlock {
    type: 'text' | 'ability' | 'action' | 'trigger';
    text?: string;
    data?: Record<string, any>;
}

const props = defineProps<{
    upgrade: {
        name: string;
        domain: string;
        faction: string | null;
        type: string | null;
        limitations: string | null;
        master_name: string | null;
        keyword_name: string | null;
        content_blocks: ContentBlock[] | null;
        back_tokens: { name: string; description: string | null }[] | null;
        back_markers: { name: string; description: string | null }[] | null;
    };
}>();

const titleCase = (value: string | null) =>
    value
        ? value
              .split('_')
              .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
              .join(' ')
        : null;

const blocks = computed(() => props.upgrade.content_blocks ?? []);
const backTokens = computed(() => props.upgrade.back_tokens ?? []);
const backMarkers = computed(() => props.upgrade.back_markers ?? []);

// Same content-length sizing as UpgradeCardRenderer.vue, so the saved image matches the live preview. Both faces share
// one box (min-height grows past the top tier rather than clipping).
const frontChars = computed(() =>
    blocks.value.reduce((sum, block) => {
        if (block.type === 'text') return sum + (block.text?.length ?? 0);
        const d = block.data;
        if (!d) return sum;
        const triggerChars = ((d.triggers as { name: string; description: string | null }[] | undefined) ?? []).reduce(
            (ts, t) => ts + (t.description?.length ?? 0) + t.name.length,
            0,
        );
        return sum + ((d.description as string | null)?.length ?? 0) + ((d.name as string | undefined)?.length ?? 0) + triggerChars;
    }, 0),
);
const backChars = computed(
    () =>
        backTokens.value.reduce((sum, t) => sum + (t.description?.length ?? 0) + t.name.length, 0) +
        backMarkers.value.reduce((sum, m) => sum + (m.description?.length ?? 0) + m.name.length, 0),
);
const size = computed(() => tarotCardSize(Math.max(frontChars.value, backChars.value)));
</script>

<template>
    <Head title="Card capture" />

    <div class="flex items-start gap-8 bg-transparent p-8">
        <div id="card-front" style="display: inline-block">
            <UpgradeFrontFace
                :name="upgrade.name"
                :domain="upgrade.domain"
                :faction="upgrade.faction"
                :upgrade-type="upgrade.type"
                :upgrade-type-label="titleCase(upgrade.type)"
                :limitations="upgrade.limitations"
                :limitations-label="titleCase(upgrade.limitations)"
                :master-name="upgrade.master_name"
                :keyword-name="upgrade.keyword_name"
                :content-blocks="blocks"
                :card-width="size.width"
                :card-min-height="size.height"
            />
        </div>
        <div id="card-back" style="display: inline-block">
            <UpgradeBackFace
                :name="upgrade.name"
                :domain="upgrade.domain"
                :faction="upgrade.faction"
                :master-name="upgrade.master_name"
                :back-tokens="backTokens"
                :back-markers="backMarkers"
                :card-width="size.width"
                :card-min-height="size.height"
            />
        </div>
    </div>
</template>
