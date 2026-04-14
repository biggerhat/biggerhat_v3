<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import GameText from '@/components/GameText.vue';
import { getFactionVar } from '@/components/CardCreator/utils';
import { computed } from 'vue';

interface TokenData {
    name: string;
    description: string | null;
}

interface MarkerData {
    name: string;
    description: string | null;
}

const props = defineProps<{
    name: string;
    domain: string;
    faction: string | null;
    masterName: string | null;
    backTokens: TokenData[];
    backMarkers: MarkerData[];
}>();

const factionVar = computed(() => getFactionVar(props.faction));
const isCrew = computed(() => props.domain === 'crew');

const nameFontSize = computed(() => {
    const len = (props.name || '').length;
    if (len > 30) return 'text-sm';
    if (len > 22) return 'text-base';
    return 'text-lg';
});

const contentScale = computed(() => {
    const total = props.backTokens.reduce((sum, t) => sum + (t.description?.length ?? 0) + t.name.length, 0) + props.backMarkers.reduce((sum, m) => sum + (m.description?.length ?? 0) + m.name.length, 0);
    if (total > 1200) return 'scale-sm';
    if (total > 800) return 'scale-md';
    if (total > 400) return 'scale-lg';
    return 'scale-xl';
});

const textSize = computed(() => {
    if (contentScale.value === 'scale-sm') return 'text-[10px] leading-[15px]';
    if (contentScale.value === 'scale-md') return 'text-[11px] leading-4';
    if (contentScale.value === 'scale-lg') return 'text-xs leading-5';
    return 'text-sm leading-5';
});
</script>

<template>
    <div class="card-face card-back relative flex h-full w-full flex-col overflow-hidden rounded-lg bg-neutral-900 text-white">
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />

        <!-- CREW BACK: Tokens & Markers -->
        <template v-if="isCrew">
            <!-- Header -->
            <div class="px-3 py-1.5" :style="{ background: `hsl(var(${factionVar}) / 0.15)` }">
                <div class="text-center">
                    <div class="font-bold uppercase tracking-wide" :class="nameFontSize">{{ name || 'Crew Card' }}</div>
                    <div v-if="masterName" class="text-[11px] text-white/60">{{ masterName }}</div>
                </div>
            </div>

            <div class="h-px" :style="{ background: `hsl(var(${factionVar}) / 0.4)` }" />

            <!-- Content -->
            <div class="flex-1 overflow-hidden px-2.5 py-2">
                <!-- Tokens -->
                <template v-if="backTokens.length">
                    <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-white/40">Tokens</div>
                    <div class="mb-3 space-y-1" :class="textSize">
                        <div v-for="token in backTokens" :key="token.name">
                            <span class="font-bold">{{ token.name }}:</span>
                            <span v-if="token.description" class="text-white/80">
                                <GameText :text="token.description" icon-class="h-3 inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Markers -->
                <template v-if="backMarkers.length">
                    <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-white/40">Markers</div>
                    <div class="space-y-1" :class="textSize">
                        <div v-for="marker in backMarkers" :key="marker.name">
                            <span class="font-bold">{{ marker.name }}:</span>
                            <span v-if="marker.description" class="text-white/80">
                                <GameText :text="marker.description" icon-class="h-3 inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="px-3 py-1.5 text-center text-[10px] font-semibold uppercase tracking-widest text-white/30" :style="{ background: `hsl(var(${factionVar}) / 0.1)` }">
                Reference
            </div>
        </template>

        <!-- CHARACTER BACK: Generic decorative design -->
        <template v-else>
            <div class="flex flex-1 flex-col items-center justify-center gap-4 p-6">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-24 opacity-20" />
                <div v-else class="size-24 rounded-full border-2 border-white/10" />
                <div class="space-y-1 text-center">
                    <div class="text-lg font-bold uppercase tracking-widest text-white/15">Malifaux</div>
                    <div class="text-2xl font-black uppercase tracking-wider text-white/20">Upgrade</div>
                    <div class="text-xs uppercase tracking-widest text-white/10">Fourth Edition</div>
                </div>
            </div>
            <div class="px-3 py-1 text-center text-[9px] uppercase tracking-widest text-white/20">Fan-Made Custom Card</div>
        </template>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />
    </div>
</template>
