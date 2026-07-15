<script setup lang="ts">
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import { tarotCardSize } from '@/components/CardCreator/utils';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const props = defineProps<{
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
        actions: ActionData[];
        abilities: AbilityData[];
        linkedCrewUpgrades: LinkedItem[];
        linkedTotems: LinkedItem[];
    };
}>();

// CardFrontFace/CardBackFace are also embedded in CardRenderer.vue's
// responsive, fixed-aspect-ratio live flip-preview elsewhere, so they stay
// flexible (h-full/w-full) rather than picking their own pixel size — the
// tarot-tiered sizing has to live here instead, in the one place that's
// capture-only. Front and back size independently since their content
// (abilities vs. actions/triggers) is unrelated.
const frontSize = computed(() => tarotCardSize(props.card.abilities.reduce((sum, a) => sum + (a.description?.length ?? 0) + a.name.length, 0)));
const backSize = computed(() =>
    tarotCardSize(
        props.card.actions.reduce((sum, a) => {
            const triggerChars = a.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0);
            return sum + (a.description?.length ?? 0) + a.name.length + triggerChars;
        }, 0),
    ),
);
</script>

<template>
    <Head title="Card capture" />

    <!-- Headless-Chrome capture target only — App\Services\Campaign\LeaderCardImageGenerator
         screenshots #card-front and #card-back individually via Browsershot's ->select().
         Sized here (not by the face components — see frontSize/backSize above)
         so the card grows to fit content instead of the face shrinking its own
         text into a fixed box. -->
    <div class="flex items-start gap-8 bg-transparent p-8">
        <div id="card-front" :style="{ width: frontSize.width + 'px', height: frontSize.height + 'px' }">
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
                :abilities="card.abilities"
                :linked-crew-upgrades="card.linkedCrewUpgrades"
                :linked-totems="card.linkedTotems"
            />
        </div>
        <div id="card-back" :style="{ width: backSize.width + 'px', height: backSize.height + 'px' }">
            <CardBackFace
                :name="card.name"
                :title="card.title"
                :faction="card.faction"
                :second-faction="card.secondFaction"
                :actions="card.actions"
                :abilities="card.abilities"
            />
        </div>
    </div>
</template>
