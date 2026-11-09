<script setup lang="ts">
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import { useTarotFit } from '@/composables/useTarotFit';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

// Headless-capture-only page — no sidebar/header/cookie-banner chrome.
// app.ts defaults every page to AppLayout unless it opts out here; without
// this, LeaderCardImageGenerator's Browsershot capture picked up the full
// site layout around the card divs, squeezing their width via flexbox.
defineOptions({ layout: null });

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
    card: {
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
    };
}>();

// Both faces share one exact tarot size, stepped up until the taller face's content fits (see useTarotFit). The faces
// only pick their own pixel size when explicitly given one (cardWidth / cardMinHeight) because they are also embedded
// in CardRenderer.vue's responsive live preview — so the sizing lives here, in the capture-only page.
const frontEl = ref<HTMLElement | null>(null);
const backEl = ref<HTMLElement | null>(null);
const { width: cardWidth, height: cardHeight, ready } = useTarotFit(frontEl, backEl);
</script>

<template>
    <Head title="Card capture" />

    <!-- Headless-Chrome capture target only — App\Services\Campaign\LeaderCardImageGenerator
         screenshots #card-front and #card-back individually via Browsershot's ->select().
         No sizing on these wrapper divs (display: inline-block just makes each
         hug its own content exactly, same as CaptureCombinedCrewCard.vue) —
         CardFrontFace/CardBackFace pick their own width/min-height directly
         (cardWidth/cardMinHeight below) so the card grows to fit content
         instead of the face shrinking its own text into a fixed box. -->
    <div class="flex items-start gap-8 bg-transparent p-8" :data-capture-ready="ready ? 'true' : 'false'">
        <div id="card-front" ref="frontEl" style="display: inline-block">
            <CardFrontFace
                :name="card.name"
                :title="card.title"
                :faction="card.faction"
                :second-faction="card.secondFaction"
                :station="card.station"
                :cost="card.cost"
                :health="card.health"
                :defense="card.defense"
                :defense-suit="card.defenseSuit"
                :willpower="card.willpower"
                :willpower-suit="card.willpowerSuit"
                :speed="card.speed"
                :size="card.size"
                :base="card.base"
                :keywords="card.keywords"
                :characteristics="card.characteristics"
                :character-image="card.characterImage"
                :character-image-focus="card.characterImageFocus"
                :abilities="card.abilities"
                :linked-crew-upgrades="card.linkedCrewUpgrades"
                :linked-totems="card.linkedTotems"
                :card-width="cardWidth"
                :card-min-height="cardHeight"
            />
        </div>
        <div id="card-back" ref="backEl" style="display: inline-block">
            <CardBackFace
                :name="card.name"
                :title="card.title"
                :faction="card.faction"
                :second-faction="card.secondFaction"
                :actions="card.actions"
                :abilities="card.abilities"
                :card-width="cardWidth"
                :card-min-height="cardHeight"
            />
        </div>
    </div>
</template>
