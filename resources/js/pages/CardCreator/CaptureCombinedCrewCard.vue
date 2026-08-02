<script setup lang="ts">
import CombinedCrewCardFace from '@/components/CardCreator/CombinedCrewCardFace.vue';
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
    is_signature?: boolean;
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

interface TextData {
    body: string;
}

interface ChoiceData {
    type: string;
    id: number | string;
    name: string;
}

interface TokenMarkerItem {
    type: 'token' | 'marker';
    id: number;
    name: string;
    description: string | null;
    base: string | null;
}

defineProps<{
    crewName: string;
    items: Array<{
        type: 'action' | 'ability' | 'trigger' | 'text' | 'choice';
        qualifier: string | null;
        source: 'starter' | 'borrowed';
        data: ActionData | AbilityData | TriggerData | TextData | ChoiceData;
    }>;
    tokensMarkers: TokenMarkerItem[];
}>();
</script>

<template>
    <Head title="Crew Card capture" />

    <!-- No sizing here — CombinedCrewCardFace picks its own width/height
         (tarot-proportioned, tiered up as content grows) since it's the one
         that knows the content volume. display: inline-block makes each
         wrapper hug its own exact box so Browsershot's element screenshot
         captures precisely one card face at a time, not a full-width
         wrapper — CombinedCrewCardImageGenerator selects #card-crew-front
         and #card-crew-back as two separate screenshots off this one page. -->
    <div class="flex flex-col gap-8 bg-transparent p-8">
        <div id="card-crew-front" style="display: inline-block">
            <CombinedCrewCardFace :crew-name="crewName" :items="items" :tokens-markers="tokensMarkers" side="front" />
        </div>
        <div id="card-crew-back" style="display: inline-block">
            <CombinedCrewCardFace :crew-name="crewName" :items="items" :tokens-markers="tokensMarkers" side="back" />
        </div>
    </div>
</template>
