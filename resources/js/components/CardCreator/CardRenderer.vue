<script setup lang="ts">
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import { tarotCardSize } from '@/components/CardCreator/utils';
import { useDebounceFn } from '@vueuse/core';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface KeywordData {
    id: number | null;
    name: string;
}

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
    source_id: number | null;
}

interface ActionData {
    name: string;
    type: string;
    is_signature: boolean;
    stone_cost: number;
    range: number | null;
    range_type: string | null;
    stat: number | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | null;
    target_suits: string | null;
    damage: string | null;
    description: string | null;
    source_id: number | null;
    triggers: TriggerData[];
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
    actions: ActionData[];
    abilities: AbilityData[];
    linkedCrewUpgrades: LinkedItem[];
    linkedTotems: LinkedItem[];
}>();

const flipped = ref(false);

// This live preview is embedded in a width-constrained column (e.g. Editor.vue's
// 1/3-width sidebar, or the Arsenal Sheet fallback) — max-width here is a
// CEILING the parent column routinely clamps below any of tarotCardSize's
// tiers, so there's nowhere for WIDTH to grow into. Rather than shrinking text
// to fit (which clips once it hits the font floor), this grows HEIGHT ONLY to
// whatever the content naturally needs at the clamped width — the preview can
// end up taller/narrower than a real card's proportions, which is expected
// and fine for a sidebar preview (same as a tall document in a narrow
// print-preview pane), but it never clips text. Exports (PNG/PDF) use their
// own dedicated, unconstrained off-screen instances instead — see
// Editor.vue's exportFrontRef/printFrontRef — so they stay properly
// tarot-proportioned regardless of this column's width.
const frontChars = computed(() => props.abilities.reduce((sum, a) => sum + (a.description?.length ?? 0) + a.name.length, 0));
const backChars = computed(() =>
    props.actions.reduce((sum, a) => {
        const triggerChars = a.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0);
        return sum + (a.description?.length ?? 0) + a.name.length + triggerChars;
    }, 0),
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

watch(
    [() => props.abilities, () => props.actions, () => props.name, () => props.title, () => props.keywords, () => props.characteristics, cardSize],
    () => nextTick(debouncedMeasure),
    { deep: true },
);

// Catches a window resize changing what width the parent column clamps this
// to (a content-prop change isn't the only thing that can shift how much
// room there is to wrap text into). Observes the outer wrapper's width only
// — not the flip container inside, whose height this same measurement sets.
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
             (containerHeight, see the doc comment above) instead of a fixed
             aspect-ratio; maxWidth still lets a narrower embedding clamp the
             width further. -->
        <div class="card-flip-container mx-auto" :style="{ maxWidth: cardSize.width + 'px', height: containerHeight + 'px', perspective: '1200px' }">
            <div
                class="card-flip-inner relative h-full w-full transition-transform duration-500"
                :style="{ transformStyle: 'preserve-3d', transform: flipped ? 'rotateY(180deg)' : '' }"
            >
                <!-- Front -->
                <div ref="frontRef" class="absolute inset-0" style="backface-visibility: hidden">
                    <CardFrontFace
                        :name="name"
                        :title="title"
                        :faction="faction"
                        :second-faction="secondFaction"
                        :station="station"
                        :cost="cost"
                        :health="health"
                        :defense="defense"
                        :defense-suit="defenseSuit"
                        :willpower="willpower"
                        :willpower-suit="willpowerSuit"
                        :speed="speed"
                        :size="size"
                        :base="base"
                        :keywords="keywords"
                        :characteristics="characteristics"
                        :character-image="characterImage"
                        :abilities="abilities"
                        :linked-crew-upgrades="linkedCrewUpgrades"
                        :linked-totems="linkedTotems"
                        :card-min-height="containerHeight"
                    />
                </div>

                <!-- Back -->
                <div ref="backRef" class="absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <CardBackFace
                        :name="name"
                        :title="title"
                        :faction="faction"
                        :second-faction="secondFaction"
                        :actions="actions"
                        :abilities="abilities"
                        :card-min-height="containerHeight"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
