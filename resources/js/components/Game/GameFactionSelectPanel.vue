<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CARD_HOVER_QUIET } from '@/lib/cardHover';
import { Check, Loader2 } from 'lucide-vue-next';

defineProps<{
    factions: Record<string, { name: string; slug: string; color: string; logo: string }>;
    isSolo: boolean;
    submitting: boolean;
    /** Highlights the card while the local player is picking on the opponent's behalf (solo). */
    isOpponentSetupPhase: boolean;
    /** The local player's locked-in faction (shown once confirmed). */
    myFaction: string | null;
    factionStepDone: boolean;
    opponentFactionStepDone: boolean;
}>();

// Shared with Master Select — the parent owns it, so it's a two-way model here.
const selectedFaction = defineModel<string | null>('selectedFaction', { required: true });
const selectedOpponentFaction = defineModel<string | null>('selectedOpponentFaction', { required: true });

defineEmits<{
    /** Submit the local player's faction (parent owns the POST + reload). */
    'confirm-faction': [];
    /** Submit the opponent's faction in solo setup. */
    'confirm-opponent-faction': [];
}>();
</script>

<template>
    <Card class="mb-6" :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''">
        <CardContent class="p-4 sm:p-6">
            <!-- Solo: two-phase faction select -->
            <template v-if="isSolo">
                <template v-if="!factionStepDone">
                    <h2 class="mb-1 text-lg font-semibold">Select Your Faction</h2>
                    <p class="mb-4 text-xs text-muted-foreground">Choose the faction you'll play this game.</p>
                    <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                        <button
                            v-for="(faction, key) in factions"
                            :key="key"
                            class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                            :class="selectedFaction === key ? 'border-primary bg-primary/10' : ['border-transparent', CARD_HOVER_QUIET]"
                            @click="selectedFaction = key as string"
                        >
                            <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12" />
                            <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                        </button>
                    </div>
                    <div v-if="selectedFaction" class="mt-4 flex justify-center">
                        <Button :disabled="submitting" @click="$emit('confirm-faction')">
                            <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                            Confirm Faction
                        </Button>
                    </div>
                </template>
                <template v-else-if="!opponentFactionStepDone">
                    <div class="mb-3 flex items-center gap-2">
                        <FactionLogo :faction="myFaction!" class-name="size-6" />
                        <Check class="size-4 text-green-500" />
                    </div>
                    <h2 class="mb-1 text-lg font-semibold">
                        Select Opponent's Faction
                        <Badge variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400">Opponent</Badge>
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">Choose the faction for your opponent.</p>
                    <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                        <button
                            v-for="(faction, key) in factions"
                            :key="key"
                            class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                            :class="selectedOpponentFaction === key ? 'border-primary bg-primary/10' : ['border-transparent', CARD_HOVER_QUIET]"
                            @click="selectedOpponentFaction = key as string"
                        >
                            <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12" />
                            <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                        </button>
                    </div>
                    <div v-if="selectedOpponentFaction" class="mt-4 flex justify-center">
                        <Button :disabled="submitting" @click="$emit('confirm-opponent-faction')">
                            <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                            Confirm Opponent Faction
                        </Button>
                    </div>
                </template>
            </template>

            <!-- Normal 2-player faction select -->
            <template v-else>
                <h2 class="mb-1 text-lg font-semibold">Select Your Faction</h2>
                <p v-if="factionStepDone" class="mb-4 text-xs text-muted-foreground">
                    <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                </p>
                <p v-else class="mb-4 text-xs text-muted-foreground">Choose the faction you'll play this game.</p>

                <template v-if="!factionStepDone">
                    <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                        <button
                            v-for="(faction, key) in factions"
                            :key="key"
                            class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                            :class="selectedFaction === key ? 'border-primary bg-primary/10' : ['border-transparent', CARD_HOVER_QUIET]"
                            @click="selectedFaction = key as string"
                        >
                            <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12" />
                            <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                        </button>
                    </div>
                    <div v-if="selectedFaction" class="mt-4 flex justify-center">
                        <Button :disabled="submitting" @click="$emit('confirm-faction')">
                            <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                            Confirm Faction
                        </Button>
                    </div>
                </template>
                <div v-else class="flex items-center justify-center gap-2 py-4">
                    <FactionLogo :faction="myFaction!" class-name="size-12" />
                    <Check class="size-5 text-green-500" />
                </div>
            </template>
        </CardContent>
    </Card>
</template>
