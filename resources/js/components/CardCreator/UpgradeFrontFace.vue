<script setup lang="ts">
import { alternatingBoxBg, formatRange, getFactionVar, splitSuits } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { useShrinkToFit } from '@/composables/useShrinkToFit';
import { computed, ref } from 'vue';

interface ContentBlock {
    type: 'text' | 'ability' | 'action' | 'trigger';
    text?: string;
    data?: Record<string, any>;
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
    /** Opt-in: only the live flip-preview (UpgradeCardRenderer.vue) and
     *  UpgradeEditor.vue's fixed-size print instance enable this — see the
     *  matching prop doc on CardFrontFace.vue. Previously unconditional here
     *  (unlike CardFrontFace/CardBackFace), which meant this content section
     *  always hard-capped at its own box via overflow-hidden even where
     *  nothing needed it to. */
    shrinkToFit?: boolean;
    /** Live-preview-only: self-sizes to a pixel min-height that grows with
     *  measured content instead of relying on shrinkToFit's font-shrink +
     *  clip — see the matching prop doc + doc comment on CardRenderer.vue.
     *  Mutually exclusive with shrinkToFit in practice (UpgradeCardRenderer.vue
     *  uses this now; the print instance still uses shrinkToFit). */
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

// Fixed em-relative tiers (no longer content-length-bucketed) — the content
// wrapper's own pixel font-size is what useShrinkToFit below adjusts, and
// since these are `em` they scale together with it, preserving the header
// < stat < body-text hierarchy at any size.
const textSize = 'text-[1em] leading-[1.35em]';
const statTextSize = 'text-[0.85em]';
const headerTextSize = 'text-[0.78em]';

const contentRef = ref<HTMLElement | null>(null);
// null target when disabled — useShrinkToFit no-ops rather than touching font-size.
const shrinkTarget = computed(() => (props.shrinkToFit ? contentRef.value : null));
useShrinkToFit(shrinkTarget, () => props.contentBlocks, { maxFontSize: 14, minFontSize: 8 });

// Exposes the root element for callers that need to capture this face
// directly (UpgradeEditor.vue's hidden print instance) rather than through
// UpgradeCardRenderer.vue's own frontRef/backRef.
const rootRef = ref<HTMLElement | null>(null);
defineExpose({ rootRef });
</script>

<template>
    <div
        ref="rootRef"
        class="card-face card-front relative flex w-full flex-col rounded-lg"
        :class="[printMode ? 'bg-white text-neutral-900' : 'bg-neutral-900 text-white', shrinkToFit ? 'h-full overflow-hidden' : '']"
        :style="[cardWidth ? { width: cardWidth + 'px' } : {}, cardMinHeight ? { minHeight: cardMinHeight + 'px' } : {}]"
    >
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />

        <!-- Type label (character upgrades) -->
        <div
            v-if="!isCrew && upgradeTypeLabel"
            class="px-3 py-1 text-center text-[11px] font-bold uppercase tracking-widest"
            :style="{ background: `hsl(var(${factionVar}) / 0.2)`, color: `hsl(var(${factionVar}))` }"
        >
            {{ upgradeTypeLabel }}
        </div>

        <!-- Header -->
        <div class="px-3 py-2" :style="{ background: `hsl(var(${factionVar}) / 0.15)` }">
            <div class="flex items-center gap-2">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-5 shrink-0" />
                <div class="min-w-0 flex-1 text-center">
                    <div class="font-bold uppercase tracking-wide" :class="nameFontSize">{{ name || 'Upgrade Name' }}</div>
                    <div v-if="isCrew && masterName" class="text-xs" :class="printMode ? 'text-neutral-500' : 'text-white/60'">{{ masterName }}</div>
                </div>
                <div v-if="faction" class="size-5 shrink-0" />
            </div>
        </div>

        <div class="h-px" :style="{ background: `hsl(var(${factionVar}) / 0.4)` }" />

        <!-- Content blocks — flex-1 gives this a measurable height budget.
             overflow-hidden is scoped to shrinkToFit=true, matching
             CardFrontFace/CardBackFace — see the shrinkToFit prop doc above.
             When shrinkToFit is true, useShrinkToFit shrinks its base font-size
             (textSize/statTextSize/headerTextSize below scale with it, being
             em-relative) to fit within it rather than clipping. -->
        <div ref="contentRef" class="flex-1 px-2.5 py-2 text-sm" :class="{ 'overflow-hidden': shrinkToFit }">
            <template v-for="(block, idx) in contentBlocks" :key="'cb-' + idx">
                <!-- Text preface -->
                <div
                    v-if="block.type === 'text' && block.text"
                    class="mb-1 font-semibold italic"
                    :class="[textSize, printMode ? 'text-neutral-500' : 'text-white/50']"
                >
                    {{ block.text }}
                </div>

                <!-- Ability -->
                <div
                    v-else-if="block.type === 'ability' && block.data"
                    class="mb-1.5 rounded px-1.5 py-1"
                    :class="textSize"
                    :style="{ background: alternatingBoxBg(factionVar, idx) }"
                >
                    <span class="font-bold">
                        <GameIcon v-if="block.data.costs_stone" type="soulstone" class-name="text-sm" />
                        <GameIcon
                            v-if="block.data.defensive_ability_type && block.data.defensive_ability_type !== 'none'"
                            :type="block.data.defensive_ability_type"
                            class-name="text-sm"
                        />
                        {{ block.data.name }}
                        <GameIcon v-if="block.data.suits && block.data.suits !== 'none'" :type="block.data.suits" class-name="text-sm" />:
                    </span>
                    <span v-if="block.data.description" :class="printMode ? 'text-neutral-700' : 'text-white/80'">
                        <GameText :text="block.data.description" icon-class="inline-block align-text-bottom" />
                    </span>
                </div>

                <!-- Action -->
                <div
                    v-else-if="block.type === 'action' && block.data"
                    class="mb-1.5 rounded"
                    :style="{ background: alternatingBoxBg(factionVar, idx) }"
                >
                    <!-- Header row -->
                    <div class="mb-0.5 flex items-center px-1.5" :class="[headerTextSize, printMode ? 'text-neutral-400' : 'text-white/40']">
                        <span class="flex-1 font-semibold uppercase tracking-wider">{{
                            block.data.type === 'tactical' ? 'Tactical Action' : 'Attack Action'
                        }}</span>
                        <span class="w-8 text-center">Rg</span>
                        <span class="w-8 text-center">Stat</span>
                        <span class="w-7 text-center">Rst</span>
                        <span class="w-8 text-center">TN</span>
                        <span class="w-8 text-center">Dmg</span>
                    </div>
                    <!-- Stat row -->
                    <div class="flex items-center px-1.5 py-1" :class="statTextSize">
                        <div class="flex min-w-0 flex-1 items-center gap-0.5 font-bold">
                            <GameIcon v-if="block.data.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                            <template v-for="n in block.data.stone_cost" :key="'sc-' + n"
                                ><GameIcon type="soulstone" class-name="text-sm shrink-0"
                            /></template>
                            <span class="truncate">{{ block.data.name }}</span>
                        </div>
                        <span class="w-8 text-center"
                            ><span class="inline-flex items-center justify-center gap-0.5"
                                ><GameIcon v-if="block.data.range_type" :type="block.data.range_type" class-name="text-xs" :size-em="1.0" />{{
                                    formatRange(block.data.range as number | string | null | undefined)
                                }}</span
                            ></span
                        >
                        <span class="w-8 text-center"
                            ><span v-if="block.data.stat != null" class="inline-flex items-center justify-center gap-0.5"
                                >{{ block.data.stat
                                }}<GameIcon
                                    v-for="s in splitSuits(block.data.stat_suits)"
                                    :key="s"
                                    :type="s"
                                    class-name="text-xs"
                                    :size-em="1.0" /></span
                            ><span v-else>-</span></span
                        >
                        <span class="w-7 text-center" :class="printMode ? 'text-neutral-500' : 'text-white/60'">{{
                            block.data.resisted_by ?? '-'
                        }}</span>
                        <span class="w-8 text-center"
                            ><span v-if="block.data.target_number != null" class="inline-flex items-center justify-center gap-0.5"
                                >{{ block.data.target_number
                                }}<GameIcon
                                    v-for="s in splitSuits(block.data.target_suits)"
                                    :key="s"
                                    :type="s"
                                    class-name="text-xs"
                                    :size-em="1.0" /></span
                            ><span v-else>-</span></span
                        >
                        <span class="w-8 text-center font-medium text-red-400">{{ block.data.damage ?? '-' }}</span>
                    </div>
                    <div v-if="block.data.description" class="px-1.5 pb-1" :class="[textSize, printMode ? 'text-neutral-700' : 'text-white/80']">
                        <GameText :text="block.data.description" icon-class="inline-block align-text-bottom" />
                    </div>
                    <!-- Triggers within action -->
                    <div
                        v-if="block.data.triggers?.length"
                        class="space-y-0.5 border-t px-1.5 py-1"
                        :class="[textSize, printMode ? 'border-neutral-200' : 'border-white/10']"
                    >
                        <div v-for="trigger in block.data.triggers" :key="trigger.name">
                            <span class="font-bold"
                                ><GameIcon v-for="s in splitSuits(trigger.suits)" :key="s" :type="s" class-name="text-xs" /> {{ trigger.name }}:</span
                            >
                            <span :class="printMode ? 'text-neutral-700' : 'text-white/80'"
                                ><GameText v-if="trigger.description" :text="trigger.description" icon-class="inline-block align-text-bottom"
                            /></span>
                        </div>
                    </div>
                </div>

                <!-- Standalone trigger -->
                <div
                    v-else-if="block.type === 'trigger' && block.data"
                    class="mb-1.5 rounded px-1.5 py-1"
                    :class="textSize"
                    :style="{ background: alternatingBoxBg(factionVar, idx) }"
                >
                    <span class="font-bold">
                        <GameIcon v-for="s in splitSuits(block.data.suits)" :key="s" :type="s" class-name="text-xs" />
                        <template v-for="n in block.data.stone_cost" :key="'tsc-' + n"><GameIcon type="soulstone" class-name="text-xs" /></template>
                        {{ block.data.name }}:
                    </span>
                    <span v-if="block.data.description" :class="printMode ? 'text-neutral-700' : 'text-white/80'">
                        <GameText :text="block.data.description" icon-class="inline-block align-text-bottom" />
                    </span>
                </div>
            </template>
        </div>

        <!-- Limitations (character upgrades) -->
        <div
            v-if="!isCrew && limitationsLabel"
            class="mx-2.5 mb-2 rounded border px-2 py-1 text-center text-[11px] font-semibold uppercase tracking-wider"
            :class="printMode ? 'border-neutral-300 text-neutral-500' : 'border-white/20 text-white/60'"
        >
            Limitations: {{ limitationsLabel }}
        </div>

        <!-- Footer -->
        <div
            class="px-3 py-1.5 text-center text-[10px] font-semibold uppercase tracking-widest"
            :class="printMode ? 'text-neutral-300' : 'text-white/30'"
            :style="{ background: `hsl(var(${factionVar}) / 0.1)` }"
        >
            {{ isCrew ? 'Crew Card' : 'Upgrade' }}
        </div>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />
    </div>
</template>
