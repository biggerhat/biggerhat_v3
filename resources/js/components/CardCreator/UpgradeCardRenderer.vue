<script setup lang="ts">
import UpgradeBackFace from '@/components/CardCreator/UpgradeBackFace.vue';
import UpgradeFrontFace from '@/components/CardCreator/UpgradeFrontFace.vue';
import { useScaledTarotPreview } from '@/composables/useScaledTarotPreview';
import { ref } from 'vue';

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
const frontRef = ref<HTMLElement | null>(null);
const backRef = ref<HTMLElement | null>(null);

// Faces render at the real tarot size the saved image would use and are scaled down to the column (see the composable).
const { rootEl, width: cardWidth, height: containerHeight, scale } = useScaledTarotPreview(
    () => frontRef.value,
    () => backRef.value,
    [() => props.contentBlocks, () => props.backTokens, () => props.backMarkers, () => props.name, () => props.limitations, () => props.masterName, () => props.keywordName],
);

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
        <div class="mx-auto overflow-hidden" :style="{ width: cardWidth * scale + 'px', height: containerHeight * scale + 'px' }">
            <div :style="{ width: cardWidth + 'px', height: containerHeight + 'px', transform: `scale(${scale})`, transformOrigin: 'top left' }">
                <div class="card-flip-container" :style="{ width: cardWidth + 'px', height: containerHeight + 'px', perspective: '1200px' }">
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
                                :card-width="cardWidth"
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
                                :card-width="cardWidth"
                                :card-min-height="containerHeight"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
