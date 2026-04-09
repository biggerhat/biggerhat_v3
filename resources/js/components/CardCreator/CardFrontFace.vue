<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { computed } from 'vue';

interface KeywordData {
    id: number | null;
    name: string;
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
}

const props = defineProps<{
    name: string;
    title: string | null;
    faction: string;
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
    abilities: AbilityData[];
}>();

const factionVar = computed(() => {
    const map: Record<string, string> = {
        arcanists: '--arcanists',
        bayou: '--bayou',
        guild: '--guild',
        explorers_society: '--explorerssociety',
        neverborn: '--neverborn',
        outcasts: '--outcasts',
        resurrectionists: '--resurrectionists',
        ten_thunders: '--tenthunders',
    };
    return map[props.faction] ?? '--primary';
});

const stationLabel = computed(() => {
    if (!props.station || props.station === 'none') return null;
    const map: Record<string, string> = { master: 'Master', enforcer: 'Enforcer', minion: 'Minion', peon: 'Peon' };
    return map[props.station] ?? props.station;
});

const displayName = computed(() => {
    return props.title ? `${props.name}, ${props.title}` : props.name;
});

const abilityFontSize = computed(() => {
    const total = props.abilities.reduce((sum, a) => sum + (a.description?.length ?? 0) + a.name.length, 0);
    if (total > 800) return 'text-[10px] leading-[14px]';
    if (total > 500) return 'text-[11px] leading-[15px]';
    return 'text-xs leading-4';
});
</script>

<template>
    <div class="card-face card-front relative flex h-full w-full flex-col overflow-hidden rounded-lg bg-neutral-900 text-white" :style="{ '--faction-color': `var(${factionVar})` }">
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />

        <!-- Header: faction logo + name + station -->
        <div class="flex items-center gap-2 px-3 py-2" :style="{ background: `hsl(var(${factionVar}) / 0.15)` }">
            <FactionLogo :faction="faction" class-name="size-6 shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-bold leading-tight">{{ displayName }}</div>
                <div v-if="stationLabel" class="text-[9px] uppercase tracking-wider text-white/60">{{ stationLabel }}</div>
            </div>
            <div v-if="cost != null" class="flex shrink-0 items-center gap-0.5 rounded-full px-2 py-1 text-sm font-bold" :style="{ background: `hsl(var(${factionVar}))` }">
                {{ cost }}<GameIcon type="soulstone" class-name="text-xs opacity-70" />
            </div>
        </div>

        <!-- Character art window -->
        <div class="relative flex-1 overflow-hidden bg-neutral-800">
            <img
                v-if="characterImage"
                :src="characterImage.startsWith('http') || characterImage.startsWith('/') || characterImage.startsWith('blob:') ? characterImage : '/storage/' + characterImage"
                :alt="name"
                class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full items-center justify-center">
                <FactionLogo :faction="faction" class-name="size-24 opacity-10" />
            </div>
            <!-- Characteristics overlay -->
            <div v-if="characteristics.length" class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent px-3 pb-1.5 pt-6">
                <div class="flex flex-wrap gap-1">
                    <span v-for="c in characteristics" :key="c" class="rounded-sm bg-white/15 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider">{{ c }}</span>
                </div>
            </div>
        </div>

        <!-- Stats bar -->
        <div class="grid grid-cols-6 divide-x divide-white/10 text-center" :style="{ background: `hsl(var(${factionVar}) / 0.2)` }">
            <div class="py-1.5">
                <div class="text-[8px] uppercase text-white/50">Df</div>
                <div class="flex items-center justify-center gap-0.5 text-sm font-bold">
                    {{ defense }}<GameIcon v-if="defenseSuit" :type="defenseSuit" class-name="text-xs" />
                </div>
            </div>
            <div class="py-1.5">
                <div class="text-[8px] uppercase text-white/50">Wp</div>
                <div class="flex items-center justify-center gap-0.5 text-sm font-bold">
                    {{ willpower }}<GameIcon v-if="willpowerSuit" :type="willpowerSuit" class-name="text-xs" />
                </div>
            </div>
            <div class="py-1.5">
                <div class="text-[8px] uppercase text-white/50">Mv</div>
                <div class="text-sm font-bold">{{ speed }}</div>
            </div>
            <div class="py-1.5">
                <div class="text-[8px] uppercase text-white/50">Sz</div>
                <div class="text-sm font-bold">{{ size ?? '—' }}</div>
            </div>
            <div class="py-1.5">
                <div class="text-[8px] uppercase text-white/50">Base</div>
                <div class="text-[10px] font-bold">{{ base }}mm</div>
            </div>
            <div class="py-1.5">
                <div class="text-[8px] uppercase text-white/50">Hp</div>
                <div class="text-sm font-bold text-red-400">{{ health }}</div>
            </div>
        </div>

        <!-- Keywords bar -->
        <div class="flex flex-wrap items-center gap-1 px-3 py-1.5 text-[8px]" :style="{ background: `hsl(var(${factionVar}) / 0.1)` }">
            <span v-for="(kw, i) in keywords" :key="kw.name" class="uppercase tracking-wider text-white/70">
                {{ kw.name }}<span v-if="i < keywords.length - 1" class="ml-1 text-white/30">|</span>
            </span>
        </div>

        <!-- Abilities -->
        <div v-if="abilities.length" class="overflow-hidden px-2.5 py-2" :class="abilityFontSize" :style="{ background: `hsl(var(${factionVar}) / 0.08)` }">
            <div v-for="ability in abilities" :key="ability.name" class="mb-1 last:mb-0">
                <span class="font-bold">
                    <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="text-sm" />
                    <GameIcon v-if="ability.defensive_ability_type && ability.defensive_ability_type !== 'none'" :type="ability.defensive_ability_type" class-name="text-sm" />
                    {{ ability.name }}
                    <GameIcon v-if="ability.suits && ability.suits !== 'none'" :type="ability.suits" class-name="text-sm" />:
                </span>
                <span v-if="ability.description" class="text-white/80">
                    <GameText :text="ability.description" icon-class="h-3 inline-block align-text-bottom" />
                </span>
            </div>
        </div>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />
    </div>
</template>
