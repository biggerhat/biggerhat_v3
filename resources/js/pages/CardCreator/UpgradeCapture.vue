<script setup lang="ts">
import UpgradeBackFace from '@/components/CardCreator/UpgradeBackFace.vue';
import UpgradeFrontFace from '@/components/CardCreator/UpgradeFrontFace.vue';
import { useTarotFit } from '@/composables/useTarotFit';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// Both faces share one exact tarot size, stepped up until the taller face's content fits (see useTarotFit).
const frontEl = ref<HTMLElement | null>(null);
const backEl = ref<HTMLElement | null>(null);
const { width: cardWidth, height: cardHeight, ready } = useTarotFit(frontEl, backEl);
</script>

<template>
    <Head title="Card capture" />

    <div class="flex items-start gap-8 bg-transparent p-8" :data-capture-ready="ready ? 'true' : 'false'">
        <div id="card-front" ref="frontEl" style="display: inline-block">
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
                :card-width="cardWidth"
                :card-min-height="cardHeight"
            />
        </div>
        <div id="card-back" ref="backEl" style="display: inline-block">
            <UpgradeBackFace
                :name="upgrade.name"
                :domain="upgrade.domain"
                :faction="upgrade.faction"
                :master-name="upgrade.master_name"
                :back-tokens="backTokens"
                :back-markers="backMarkers"
                :card-width="cardWidth"
                :card-min-height="cardHeight"
            />
        </div>
    </div>
</template>
