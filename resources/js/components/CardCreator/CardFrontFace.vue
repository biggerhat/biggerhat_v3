<script setup lang="ts">
import ArtImage from '@/components/CardCreator/ArtImage.vue';
import { alternatingBoxBg, factionGradient, getFactionVar } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { useShrinkToFit } from '@/composables/useShrinkToFit';
import { computed, ref } from 'vue';

interface KeywordData {
    id: number | null;
    name: string;
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
}

interface LinkedItem {
    source_type: 'official' | 'custom';
    id: number;
    name: string;
}

const props = defineProps<{
    name: string;
    title: string | null;
    faction: string | null;
    secondFaction: string | null;
    station: string;
    cost: number | null;
    health: number;
    defense: number;
    defenseSuit: string | null;
    willpower: number;
    willpowerSuit: string | null;
    speed: number;
    size: number | null;
    base: string;
    keywords: KeywordData[];
    characteristics: string[];
    characterImage: string | null;
    /** How the art is framed in the window (see ArtImage.vue); defaults to centred, unzoomed. */
    characterImageFocus?: { x: number; y: number; zoom: number; dx?: number; dy?: number } | null;
    abilities: AbilityData[];
    linkedCrewUpgrades: LinkedItem[];
    linkedTotems: LinkedItem[];
    /** Opt-in: only the live flip-preview (CardRenderer.vue) enables this.
     *  Capture.vue's headless capture sizes this root directly instead (see
     *  cardWidth/cardMinHeight below) — enabling both at once is exactly the
     *  "fighting the box" bug that was previously fixed here. */
    shrinkToFit?: boolean;
    /** Capture.vue-only: this root picks its own width/min-height (tarot-
     *  tiered, growing with content — see tarotCardSize() in utils.ts) the
     *  same way CombinedCrewCardFace already does for the Crew Card, instead
     *  of filling a fixed-size wrapper (w-full/h-full) — min-height (not a
     *  hard height) plus no overflow-hidden means content past even the top
     *  tier grows the box further rather than clipping. Unset (falls back to
     *  w-full/h-full) for the live preview, where CardRenderer.vue's own
     *  flip-container owns sizing instead. */
    cardWidth?: number;
    cardMinHeight?: number;
    /** Print-only light theme (saves ink). Hp is the same on every card: max
     *  Hp number in the stat grid, pips along the bottom edge. Only the
     *  dedicated hidden print-capture instance in Editor.vue sets this. */
    printMode?: boolean;
}>();

const factionVar = computed(() => getFactionVar(props.faction));
const secondFactionVar = computed(() => (props.secondFaction ? getFactionVar(props.secondFaction) : null));
const hasDualFaction = computed(() => !!secondFactionVar.value);
const borderGradient = computed(() => factionGradient(factionVar.value, secondFactionVar.value));

const stationLabel = computed(() => {
    if (!props.station || props.station === 'none') return null;
    const map: Record<string, string> = { master: 'Master', enforcer: 'Enforcer', minion: 'Minion', peon: 'Peon' };
    return map[props.station] ?? props.station;
});

const displayName = computed(() => {
    return props.title ? `${props.name}, ${props.title}` : props.name;
});

const nameFontSize = computed(() => {
    const len = displayName.value.length;
    if (len > 28) return 'text-sm';
    if (len > 22) return 'text-base';
    return 'text-lg';
});

// This face is embedded two ways: the fixed-size headless capture page
// (Capture.vue, which sizes its own wrapper div to fit content — see
// tarotCardSize() in utils.ts) and CardRenderer.vue's responsive, fixed-
// aspect-ratio live flip-preview (used across the Card Creator tools and the
// Arsenal Sheet) — so this component itself stays a flexible h-full/w-full
// box rather than picking its own pixel size. Only the live-preview path
// opts into shrinkToFit (see prop doc above); Capture.vue leaves it off and
// keeps growing its own wrapper to fit content instead.
const abilitiesRef = ref<HTMLElement | null>(null);
// null target when disabled — useShrinkToFit no-ops rather than touching font-size.
const shrinkTarget = computed(() => (props.shrinkToFit ? abilitiesRef.value : null));
useShrinkToFit(shrinkTarget, () => [props.abilities, props.name, props.title], { maxFontSize: 16, minFontSize: 9 });

// Exposes the root element for callers that need to capture this face
// directly (Editor.vue's hidden print instance) rather than through
// CardRenderer.vue's own frontRef/backRef.
const rootRef = ref<HTMLElement | null>(null);
defineExpose({ rootRef });
</script>

<template>
    <div
        ref="rootRef"
        class="card-face card-front relative flex w-full flex-col rounded-lg"
        :class="[printMode ? 'bg-white text-neutral-900' : 'bg-neutral-900 text-white', shrinkToFit ? 'h-full overflow-hidden' : '']"
        :style="[
            { '--faction-color': `var(${factionVar})` },
            cardWidth ? { width: cardWidth + 'px' } : {},
            cardMinHeight ? { minHeight: cardMinHeight + 'px' } : {},
        ]"
    >
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: borderGradient }" />

        <!-- Header: faction logo + name + station -->
        <div
            class="flex items-center gap-2 px-3 py-2"
            :style="{
                background: hasDualFaction
                    ? `linear-gradient(to right, hsl(var(${factionVar}) / 0.15), hsl(var(${secondFactionVar}) / 0.15))`
                    : `hsl(var(${factionVar}) / 0.15)`,
            }"
        >
            <div v-if="faction || secondFaction" class="flex shrink-0 items-center gap-0.5">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-6" />
                <FactionLogo v-if="secondFaction" :faction="secondFaction" class-name="size-5" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="font-bold leading-snug" :class="nameFontSize">{{ displayName }}</div>
                <div
                    v-if="stationLabel"
                    class="mt-0.5 text-[11px] uppercase tracking-wider"
                    :class="printMode ? 'text-neutral-500' : 'text-white/60'"
                >
                    {{ stationLabel }}
                </div>
            </div>
            <div
                v-if="cost != null"
                class="flex shrink-0 items-center gap-0.5 rounded-full px-2 py-1 text-lg font-bold"
                :style="{ background: `hsl(var(${factionVar}))` }"
            >
                {{ cost }}<GameIcon type="soulstone" class-name="text-xs opacity-70" :size-em="1.0" />
            </div>
        </div>

        <!-- Character art window — fixed aspect (not flex-1): the portrait
             shouldn't shrink to make room for ability text; the abilities
             section below grows to fit instead (see CardRenderer.vue /
             useTarotGrowToFit). max-h caps this at tier-0's natural 440px
             regardless of how wide the card grows beyond 550 — without it,
             aspect-[550/440] keeps scaling this (often-empty) placeholder
             right along with card width, so growing the card to fit more
             text also proportionally re-inflates the art window, which
             doesn't hold any text — needlessly forcing much bigger tiers
             than the ability content actually needs. -->
        <div class="relative aspect-[550/440] max-h-[440px] w-full shrink-0 overflow-hidden" :class="printMode ? 'bg-neutral-100' : 'bg-neutral-800'">
            <ArtImage
                v-if="characterImage"
                :src="
                    characterImage.startsWith('http') || characterImage.startsWith('/') || characterImage.startsWith('blob:')
                        ? characterImage
                        : '/storage/' + characterImage
                "
                :alt="name"
                :x="characterImageFocus?.x ?? 50"
                :y="characterImageFocus?.y ?? 50"
                :zoom="characterImageFocus?.zoom ?? 100"
                :dx="characterImageFocus?.dx ?? 0"
                :dy="characterImageFocus?.dy ?? 0"
            />
            <div v-else class="flex h-full items-center justify-center gap-2">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-24 opacity-10" />
                <FactionLogo v-if="secondFaction" :faction="secondFaction" class-name="size-20 opacity-10" />
            </div>
            <!-- Characteristics overlay -->
            <div
                v-if="characteristics.length"
                class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent px-3 pb-1.5 pt-6"
            >
                <div class="flex flex-wrap gap-1">
                    <span
                        v-for="c in characteristics"
                        :key="c"
                        class="rounded-sm bg-white/15 px-1.5 py-0.5 text-xs font-medium uppercase tracking-wider"
                        >{{ c }}</span
                    >
                </div>
            </div>
        </div>

        <!-- Stats bar — Hp shows the max number; the pips live in the bottom line. -->
        <div
            class="grid grid-cols-6 divide-x text-center"
            :class="printMode ? 'divide-neutral-200' : 'divide-white/10'"
            :style="{ background: `hsl(var(${factionVar}) / 0.2)` }"
        >
            <div class="py-1.5">
                <div class="text-[10px] uppercase" :class="printMode ? 'text-neutral-500' : 'text-white/50'">Df</div>
                <div class="flex items-center justify-center gap-0.5 text-lg font-bold">
                    {{ defense }}<GameIcon v-if="defenseSuit" :type="defenseSuit" class-name="text-xs" :size-em="1.0" />
                </div>
            </div>
            <div class="py-1.5">
                <div class="text-[10px] uppercase" :class="printMode ? 'text-neutral-500' : 'text-white/50'">Wp</div>
                <div class="flex items-center justify-center gap-0.5 text-lg font-bold">
                    {{ willpower }}<GameIcon v-if="willpowerSuit" :type="willpowerSuit" class-name="text-xs" :size-em="1.0" />
                </div>
            </div>
            <div class="py-1.5">
                <div class="text-[10px] uppercase" :class="printMode ? 'text-neutral-500' : 'text-white/50'">Mv</div>
                <div class="text-lg font-bold">{{ speed }}</div>
            </div>
            <div class="py-1.5">
                <div class="text-[10px] uppercase" :class="printMode ? 'text-neutral-500' : 'text-white/50'">Sz</div>
                <div class="text-lg font-bold">{{ size ?? '—' }}</div>
            </div>
            <div class="py-1.5">
                <div class="text-[10px] uppercase" :class="printMode ? 'text-neutral-500' : 'text-white/50'">Base</div>
                <div class="text-xs font-bold">{{ base }}mm</div>
            </div>
            <div class="py-1.5">
                <div class="text-[10px] uppercase" :class="printMode ? 'text-neutral-500' : 'text-white/50'">Hp</div>
                <div class="flex items-center justify-center gap-0.5 text-lg font-bold">
                    {{ health }}<GameIcon type="soulstone" class-name="text-xs" :size-em="1.0" />
                </div>
            </div>
        </div>

        <!-- Keywords bar -->
        <div class="flex flex-wrap items-center gap-1 px-3 py-1.5 text-[11px]" :style="{ background: `hsl(var(${factionVar}) / 0.1)` }">
            <span
                v-for="(kw, i) in keywords"
                :key="kw.name"
                class="uppercase tracking-wider"
                :class="printMode ? 'text-neutral-600' : 'text-white/70'"
            >
                {{ kw.name }}<span v-if="i < keywords.length - 1" class="ml-1" :class="printMode ? 'text-neutral-300' : 'text-white/30'">|</span>
            </span>
        </div>

        <!-- Linked Crew Upgrades & Totems -->
        <div
            v-if="linkedCrewUpgrades.length || linkedTotems.length"
            class="space-y-0.5 px-2.5 py-1 text-[11px]"
            :style="{ background: `hsl(var(${factionVar}) / 0.05)` }"
        >
            <div v-if="linkedCrewUpgrades.length" class="flex items-center gap-1">
                <span class="shrink-0 font-semibold uppercase tracking-wider" :class="printMode ? 'text-neutral-400' : 'text-white/40'">Crew:</span>
                <span v-for="(u, i) in linkedCrewUpgrades" :key="'cu-' + i" :class="printMode ? 'text-neutral-600' : 'text-white/70'">
                    {{ u.name }}<span v-if="i < linkedCrewUpgrades.length - 1" :class="printMode ? 'text-neutral-300' : 'text-white/30'">,</span>
                </span>
            </div>
            <div v-if="linkedTotems.length" class="flex items-center gap-1">
                <span class="shrink-0 font-semibold uppercase tracking-wider" :class="printMode ? 'text-neutral-400' : 'text-white/40'">Totem:</span>
                <span v-for="(t, i) in linkedTotems" :key="'totem-' + i" :class="printMode ? 'text-neutral-600' : 'text-white/70'">
                    {{ t.name }}<span v-if="i < linkedTotems.length - 1" :class="printMode ? 'text-neutral-300' : 'text-white/30'">,</span>
                </span>
            </div>
        </div>

        <!-- Abilities — flex-1 gives this section a measurable height budget
             (kept unconditional, even with no abilities, so it stays the
             flex-1 spacer absorbing leftover height below the fixed-aspect
             art window instead of leaving a gap above the bottom border).
             overflow-hidden is scoped to shrinkToFit=true (the live preview,
             which measures against it to decide how far to shrink text) —
             the capture path (shrinkToFit=false, useShrinkToFit a no-op)
             leaves it off so pathologically long content grows past
             CardRenderer.vue/Capture.vue's own tarotCardSize() tier instead
             of silently clipping. -->
        <div ref="abilitiesRef" class="flex-1 px-2.5 py-2 text-base leading-6" :class="{ 'overflow-hidden': shrinkToFit }">
            <div
                v-for="(ability, idx) in abilities"
                :key="ability.name"
                class="mb-1.5 rounded px-1.5 py-1 last:mb-0"
                :style="{ background: alternatingBoxBg(factionVar, idx) }"
            >
                <span class="font-bold">
                    <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="text-sm" />
                    <GameIcon
                        v-if="ability.defensive_ability_type && ability.defensive_ability_type !== 'none'"
                        :type="ability.defensive_ability_type"
                        class-name="text-sm"
                    />
                    {{ ability.name }}
                    <GameIcon v-if="ability.suits && ability.suits !== 'none'" :type="ability.suits" class-name="text-sm" />:
                </span>
                <span v-if="ability.description" :class="printMode ? 'text-neutral-700' : 'text-white/80'">
                    <GameText :text="ability.description" icon-class="inline-block align-text-bottom" />
                </span>
            </div>
        </div>

        <!-- Hp pip line along the bottom edge, like the official M4E card layout. -->
        <div
            class="flex flex-wrap items-center justify-center gap-1.5 border-t px-3 py-1.5"
            :class="printMode ? 'border-neutral-200' : 'border-white/10'"
            :style="{ background: `hsl(var(${factionVar}) / ${printMode ? 0.08 : 0.2})` }"
        >
            <span class="mr-1 text-[10px] font-semibold uppercase tracking-wider" :class="printMode ? 'text-neutral-400' : 'text-white/50'">Hp</span>
            <span
                v-for="n in health"
                :key="'hp-' + n"
                class="size-2.5 shrink-0 rounded-full border-2"
                :class="printMode ? 'border-red-500' : 'border-red-400 bg-red-400'"
            />
        </div>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: borderGradient }" />
    </div>
</template>
