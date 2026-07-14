<script setup lang="ts">
import CrewCardFace from '@/components/CardCreator/CrewCardFace.vue';
import { Head } from '@inertiajs/vue3';

// Headless-capture-only page — see CardCreator/Capture.vue for why layout is
// disabled (Browsershot's capture otherwise picks up the full site chrome).
defineOptions({ layout: null });

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
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
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
}

defineProps<{
    card: {
        name: string;
        body: string | null;
        masterName: string | null;
        masterFaction: string | null;
        abilities: AbilityData[];
        actions: ActionData[];
    };
}>();
</script>

<template>
    <Head title="Crew Card capture" />

    <!-- Single face only — unlike a full Leader/Totem stat card, a Crew Card
         has no separate front/back split. Same fixed 550x950 pixel size so
         App\Services\Campaign\CrewCardImageGenerator's Browsershot capture
         has a deterministic bounding box. -->
    <div class="bg-transparent p-8">
        <div id="card-crew" style="width: 550px; height: 950px">
            <CrewCardFace
                :name="card.name"
                :body="card.body"
                :master-name="card.masterName"
                :master-faction="card.masterFaction"
                :abilities="card.abilities"
                :actions="card.actions"
            />
        </div>
    </div>
</template>
