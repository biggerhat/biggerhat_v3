<script setup lang="ts">
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
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

defineProps<{
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

// Expose refs for export capture
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
                    />
                </div>
            </div>
        </div>
    </div>
</template>
