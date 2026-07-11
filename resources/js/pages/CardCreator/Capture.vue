<script setup lang="ts">
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import { Head } from '@inertiajs/vue3';

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
        actions: ActionData[];
        abilities: AbilityData[];
        linkedCrewUpgrades: LinkedItem[];
        linkedTotems: LinkedItem[];
    };
}>();
</script>

<template>
    <Head title="Card capture" />

    <!-- Headless-Chrome capture target only — App\Services\Campaign\LeaderCardImageGenerator
         screenshots #card-front and #card-back individually via Browsershot's ->select().
         Fixed pixel size (not the flexible aspect-ratio the interactive CardRenderer uses)
         so the capture has a deterministic bounding box regardless of viewport. -->
    <div class="flex gap-8 bg-transparent p-8">
        <div id="card-front" style="width: 550px; height: 950px">
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
        <div id="card-back" style="width: 550px; height: 950px">
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
