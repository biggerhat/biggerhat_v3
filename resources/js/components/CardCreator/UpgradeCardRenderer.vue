<script setup lang="ts">
import UpgradeBackFace from '@/components/CardCreator/UpgradeBackFace.vue';
import UpgradeFrontFace from '@/components/CardCreator/UpgradeFrontFace.vue';
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

defineProps<{
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

const frontRef = ref<HTMLElement | null>(null);
const backRef = ref<HTMLElement | null>(null);

defineExpose({ frontRef, backRef });
</script>

<template>
    <div class="card-renderer">
        <!-- Flip toggle -->
        <div class="mb-2 flex justify-center">
            <button class="rounded-md border px-3 py-1 text-xs transition-colors hover:bg-accent" @click="flipped = !flipped">
                {{ flipped ? 'Show Front' : 'Show Back' }}
            </button>
        </div>

        <!-- Card container with 3D flip -->
        <div class="card-flip-container mx-auto aspect-[550/950]" style="perspective: 1200px">
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
                    />
                </div>
            </div>
        </div>
    </div>
</template>
