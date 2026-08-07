<script setup lang="ts">
import UpgradeBackFace from '@/components/CardCreator/UpgradeBackFace.vue';
import UpgradeFrontFace from '@/components/CardCreator/UpgradeFrontFace.vue';
import { tarotCardSize } from '@/components/CardCreator/utils';
import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface ContentBlock {
    type: 'text' | 'ability' | 'action' | 'trigger';
    text?: string;
    data?: Record<string, any>;
}

interface TokenData {
    name: string;
    description: string | null;
}

interface MarkerData {
    name: string;
    description: string | null;
}

const props = defineProps<{
    name: string;
    domain: string;
    faction: string | null;
    upgradeType: string | null;
    upgradeTypeLabel: string | null;
    limitations: string | null;
    limitationsLabel: string | null;
    masterName: string | null;
    keywordName: string | null;
    contentBlocks: ContentBlock[];
    backTokens: TokenData[];
    backMarkers: MarkerData[];
}>();

const flipped = ref(false);

// Same width-constrained-column reasoning as CardRenderer.vue — see its doc
// comment. Grows HEIGHT ONLY to fit real content at whatever width the
// parent column clamps this to (never clips); exports use their own
// unconstrained instances instead.
const frontChars = computed(() =>
    props.contentBlocks.reduce((sum, block) => {
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
        props.backTokens.reduce((sum, t) => sum + (t.description?.length ?? 0) + t.name.length, 0) +
        props.backMarkers.reduce((sum, m) => sum + (m.description?.length ?? 0) + m.name.length, 0),
);
const cardSize = computed(() => tarotCardSize(Math.max(frontChars.value, backChars.value)));

const frontRef = ref<HTMLElement | null>(null);
const backRef = ref<HTMLElement | null>(null);

const containerHeight = ref(cardSize.value.height);
const measure = () => {
    const frontHeight = frontRef.value?.scrollHeight ?? 0;
    const backHeight = backRef.value?.scrollHeight ?? 0;
    containerHeight.value = Math.max(cardSize.value.height, frontHeight, backHeight);
};
const debouncedMeasure = useDebounceFn(measure, 150);

watch([() => props.contentBlocks, () => props.backTokens, () => props.backMarkers, () => props.name, cardSize], () => nextTick(debouncedMeasure), {
    deep: true,
});

// Catches a window resize changing what width the parent column clamps this
// to — see CardRenderer.vue's identical implementation for the full
// rationale.
const rootEl = ref<HTMLElement | null>(null);
let lastWidth = 0;
let resizeObserver: ResizeObserver | null = null;
onMounted(() => {
    nextTick(measure);
    if (rootEl.value) {
        lastWidth = rootEl.value.getBoundingClientRect().width;
        resizeObserver = new ResizeObserver((entries) => {
            const width = entries[0]?.contentRect.width ?? lastWidth;
            if (Math.abs(width - lastWidth) > 0.5) {
                lastWidth = width;
                debouncedMeasure();
            }
        });
        resizeObserver.observe(rootEl.value);
    }
});
onUnmounted(() => resizeObserver?.disconnect());

defineExpose({ frontRef, backRef });
</script>

<template>
    <div ref="rootEl" class="card-renderer">
        <!-- Flip toggle -->
        <div class="mb-2 flex justify-center">
            <button class="rounded-md border px-3 py-1 text-xs transition-colors hover:bg-accent" @click="flipped = !flipped">
                {{ flipped ? 'Show Front' : 'Show Back' }}
            </button>
        </div>

        <!-- Card container with 3D flip. Height grows to fit real content
             (containerHeight) instead of a fixed aspect-ratio. -->
        <div class="card-flip-container mx-auto" :style="{ maxWidth: cardSize.width + 'px', height: containerHeight + 'px', perspective: '1200px' }">
            <div
                class="card-flip-inner relative h-full w-full transition-transform duration-500"
                :style="{ transformStyle: 'preserve-3d', transform: flipped ? 'rotateY(180deg)' : '' }"
            >
                <!-- Front -->
                <div ref="frontRef" class="absolute inset-0" style="backface-visibility: hidden">
                    <UpgradeFrontFace
                        :name="name"
                        :domain="domain"
                        :faction="faction"
                        :upgrade-type="upgradeType"
                        :upgrade-type-label="upgradeTypeLabel"
                        :limitations="limitations"
                        :limitations-label="limitationsLabel"
                        :master-name="masterName"
                        :keyword-name="keywordName"
                        :content-blocks="contentBlocks"
                        :card-min-height="containerHeight"
                    />
                </div>

                <!-- Back -->
                <div ref="backRef" class="absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <UpgradeBackFace
                        :name="name"
                        :domain="domain"
                        :faction="faction"
                        :master-name="masterName"
                        :back-tokens="backTokens"
                        :back-markers="backMarkers"
                        :card-min-height="containerHeight"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
