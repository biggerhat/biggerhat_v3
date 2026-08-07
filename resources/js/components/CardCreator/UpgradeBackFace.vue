<script setup lang="ts">
import { getFactionVar } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import GameText from '@/components/GameText.vue';
import { useShrinkToFit } from '@/composables/useShrinkToFit';
import { computed, ref } from 'vue';

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
    masterName: string | null;
    backTokens: TokenData[];
    backMarkers: MarkerData[];
    /** Opt-in: only the live flip-preview (UpgradeCardRenderer.vue) and
     *  UpgradeEditor.vue's fixed-size print instance enable this — see the
     *  matching prop doc on UpgradeFrontFace.vue/CardFrontFace.vue. */
    shrinkToFit?: boolean;
    /** Live-preview-only self-sizing — see the matching prop doc on
     *  UpgradeFrontFace.vue. */
    cardWidth?: number;
    cardMinHeight?: number;
    /** Print-only light theme (saves ink) — see the matching prop doc on
     *  CardFrontFace.vue. */
    printMode?: boolean;
}>();

const factionVar = computed(() => getFactionVar(props.faction));
const isCrew = computed(() => props.domain === 'crew');

const nameFontSize = computed(() => {
    const len = (props.name || '').length;
    if (len > 30) return 'text-sm';
    if (len > 22) return 'text-base';
    return 'text-lg';
});

const contentRef = ref<HTMLElement | null>(null);
// null target when disabled — useShrinkToFit no-ops rather than touching font-size.
const shrinkTarget = computed(() => (props.shrinkToFit ? contentRef.value : null));
useShrinkToFit(shrinkTarget, () => [props.backTokens, props.backMarkers], { maxFontSize: 14, minFontSize: 8 });

// Exposes the root element for callers that need to capture this face
// directly (UpgradeEditor.vue's hidden print instance) rather than through
// UpgradeCardRenderer.vue's own frontRef/backRef.
const rootRef = ref<HTMLElement | null>(null);
defineExpose({ rootRef });
</script>

<template>
    <div
        ref="rootRef"
        class="card-face card-back relative flex w-full flex-col rounded-lg"
        :class="[printMode ? 'bg-white text-neutral-900' : 'bg-neutral-900 text-white', shrinkToFit ? 'h-full overflow-hidden' : '']"
        :style="[cardWidth ? { width: cardWidth + 'px' } : {}, cardMinHeight ? { minHeight: cardMinHeight + 'px' } : {}]"
    >
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />

        <!-- CREW BACK: Tokens & Markers -->
        <template v-if="isCrew">
            <!-- Header -->
            <div class="px-3 py-1.5" :style="{ background: `hsl(var(${factionVar}) / 0.15)` }">
                <div class="text-center">
                    <div class="font-bold uppercase tracking-wide" :class="nameFontSize">{{ name || 'Crew Card' }}</div>
                    <div v-if="masterName" class="text-[11px]" :class="printMode ? 'text-neutral-500' : 'text-white/60'">{{ masterName }}</div>
                </div>
            </div>

            <div class="h-px" :style="{ background: `hsl(var(${factionVar}) / 0.4)` }" />

            <!-- Content — flex-1 already gives this a measurable height
                 budget. overflow-hidden is scoped to shrinkToFit=true,
                 matching CardFrontFace/CardBackFace — see the shrinkToFit
                 prop doc above. When true, useShrinkToFit shrinks its base
                 font-size to fit within it rather than clipping. -->
            <div ref="contentRef" class="flex-1 px-2.5 py-2 text-sm leading-5" :class="{ 'overflow-hidden': shrinkToFit }">
                <!-- Tokens -->
                <template v-if="backTokens.length">
                    <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider" :class="printMode ? 'text-neutral-400' : 'text-white/40'">
                        Tokens
                    </div>
                    <div class="mb-3 space-y-1">
                        <div v-for="token in backTokens" :key="token.name">
                            <span class="font-bold">{{ token.name }}:</span>
                            <span v-if="token.description" :class="printMode ? 'text-neutral-700' : 'text-white/80'">
                                <GameText :text="token.description" icon-class="inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Markers -->
                <template v-if="backMarkers.length">
                    <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider" :class="printMode ? 'text-neutral-400' : 'text-white/40'">
                        Markers
                    </div>
                    <div class="space-y-1">
                        <div v-for="marker in backMarkers" :key="marker.name">
                            <span class="font-bold">{{ marker.name }}:</span>
                            <span v-if="marker.description" :class="printMode ? 'text-neutral-700' : 'text-white/80'">
                                <GameText :text="marker.description" icon-class="inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div
                class="px-3 py-1.5 text-center text-[10px] font-semibold uppercase tracking-widest"
                :class="printMode ? 'text-neutral-300' : 'text-white/30'"
                :style="{ background: `hsl(var(${factionVar}) / 0.1)` }"
            >
                Reference
            </div>
        </template>

        <!-- CHARACTER BACK: Generic decorative design -->
        <template v-else>
            <div class="flex flex-1 flex-col items-center justify-center gap-4 p-6">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-24 opacity-20" />
                <div v-else class="size-24 rounded-full border-2" :class="printMode ? 'border-neutral-200' : 'border-white/10'" />
                <div class="space-y-1 text-center">
                    <div class="text-lg font-bold uppercase tracking-widest" :class="printMode ? 'text-neutral-300' : 'text-white/15'">Malifaux</div>
                    <div class="text-2xl font-black uppercase tracking-wider" :class="printMode ? 'text-neutral-400' : 'text-white/20'">Upgrade</div>
                    <div class="text-xs uppercase tracking-widest" :class="printMode ? 'text-neutral-200' : 'text-white/10'">Fourth Edition</div>
                </div>
            </div>
            <div class="px-3 py-1 text-center text-[9px] uppercase tracking-widest" :class="printMode ? 'text-neutral-300' : 'text-white/20'">
                Fan-Made Custom Card
            </div>
        </template>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />
    </div>
</template>
