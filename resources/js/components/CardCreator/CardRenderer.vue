<script setup lang="ts">
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import { useScaledTarotPreview } from '@/composables/useScaledTarotPreview';
import { ref } from 'vue';

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
    characterImageFocus?: { x: number; y: number; zoom: number; dx?: number; dy?: number } | null;
    actions: ActionData[];
    abilities: AbilityData[];
    linkedCrewUpgrades: LinkedItem[];
    linkedTotems: LinkedItem[];
}>();

const flipped = ref(false);

const frontRef = ref<HTMLElement | null>(null);
const backRef = ref<HTMLElement | null>(null);

// Faces render at the real tarot size the saved image would use and are scaled down to the column (see the composable).
const {
    rootEl,
    width: cardWidth,
    height: containerHeight,
    scale,
} = useScaledTarotPreview(
    () => frontRef.value,
    () => backRef.value,
    [
        () => props.abilities,
        () => props.actions,
        () => props.name,
        () => props.title,
        () => props.keywords,
        () => props.characteristics,
        () => props.characterImage,
        () => props.characterImageFocus,
        () => props.faction,
        () => props.secondFaction,
        () => props.linkedCrewUpgrades,
        () => props.linkedTotems,
    ],
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
             (containerHeight, see the doc comment above) instead of a fixed
             aspect-ratio; maxWidth still lets a narrower embedding clamp the
             width further. -->
        <div class="mx-auto overflow-hidden" :style="{ width: cardWidth * scale + 'px', height: containerHeight * scale + 'px' }">
            <div :style="{ width: cardWidth + 'px', height: containerHeight + 'px', transform: `scale(${scale})`, transformOrigin: 'top left' }">
                <div class="card-flip-container" :style="{ width: cardWidth + 'px', height: containerHeight + 'px', perspective: '1200px' }">
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
                                :character-image-focus="characterImageFocus"
                                :abilities="abilities"
                                :linked-crew-upgrades="linkedCrewUpgrades"
                                :linked-totems="linkedTotems"
                                :card-width="cardWidth"
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
