<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { factionGradient, formatRange, getFactionVar, splitSuits } from '@/components/CardCreator/utils';
import { computed } from 'vue';

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

const props = defineProps<{
    name: string;
    title: string | null;
    faction: string | null;
    secondFaction: string | null;
    actions: ActionData[];
    abilities: AbilityData[];
}>();

const factionVar = computed(() => getFactionVar(props.faction));
const secondFactionVar = computed(() => (props.secondFaction ? getFactionVar(props.secondFaction) : null));
const hasDualFaction = computed(() => !!secondFactionVar.value);
const borderGradient = computed(() => factionGradient(factionVar.value, secondFactionVar.value));

const displayName = computed(() => (props.title ? `${props.name}, ${props.title}` : props.name));

const nameFontSize = computed(() => {
    const len = displayName.value.length;
    if (len > 35) return 'text-xs';
    if (len > 28) return 'text-sm';
    return 'text-base';
});

// Sort: attack actions first, then tactical
const attackActions = computed(() => props.actions.filter((a) => a.type !== 'tactical'));
const tacticalActions = computed(() => props.actions.filter((a) => a.type === 'tactical'));

// Auto-scale font size based on content volume
const contentScale = computed(() => {
    const actionChars = props.actions.reduce((sum, a) => {
        const triggerChars = a.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0);
        return sum + (a.description?.length ?? 0) + a.name.length + triggerChars;
    }, 0);
    if (actionChars > 1500) return 'scale-sm';
    if (actionChars > 1000) return 'scale-md';
    if (actionChars > 600) return 'scale-lg';
    return 'scale-xl';
});
</script>

<template>
    <div class="card-face card-back relative flex h-full w-full flex-col overflow-hidden rounded-lg bg-neutral-900 text-white">
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: borderGradient }" />

        <!-- Header -->
        <div
            class="flex items-center gap-2 px-3 py-1.5"
            :style="{
                background: hasDualFaction
                    ? `linear-gradient(to right, hsl(var(${factionVar}) / 0.15), hsl(var(${secondFactionVar}) / 0.15))`
                    : `hsl(var(${factionVar}) / 0.15)`,
            }"
        >
            <div v-if="faction || secondFaction" class="flex shrink-0 items-center gap-0.5">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-4" />
                <FactionLogo v-if="secondFaction" :faction="secondFaction" class-name="size-3.5" />
            </div>
            <div class="truncate font-bold" :class="nameFontSize">{{ displayName }}</div>
        </div>

        <!-- Content area -->
        <div class="flex-1 overflow-hidden px-2 py-1.5">
            <!-- Attack Actions -->
            <template v-if="attackActions.length">
                <!-- Section header row -->
                <div class="mb-0.5 flex items-center px-1.5 text-white/40" :class="contentScale === 'scale-sm' ? 'text-[9px]' : 'text-[10px]'">
                    <span class="flex-1 font-semibold uppercase tracking-wider">Attack Actions</span>
                    <span class="w-8 text-center">Rg</span>
                    <span class="w-8 text-center">Stat</span>
                    <span class="w-7 text-center">Rst</span>
                    <span class="w-8 text-center">TN</span>
                    <span class="w-8 text-center">Dmg</span>
                </div>

                <div v-for="action in attackActions" :key="'atk-' + action.name" class="mb-1.5 rounded" :style="{ background: `hsl(var(${factionVar}) / 0.08)` }">
                    <!-- Stat row -->
                    <div class="flex items-center px-1.5 py-1" :class="contentScale === 'scale-sm' ? 'text-[11px]' : contentScale === 'scale-md' ? 'text-xs' : 'text-sm'">
                        <div class="flex min-w-0 flex-1 items-center gap-0.5 font-bold">
                            <GameIcon v-if="action.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                            <template v-for="n in action.stone_cost" :key="'sc-' + n">
                                <GameIcon type="soulstone" class-name="text-sm shrink-0" />
                            </template>
                            <span class="truncate">{{ action.name }}</span>
                        </div>
                        <span class="w-8 text-center">
                            <span class="inline-flex items-center justify-center gap-0.5">
                                <GameIcon v-if="action.range_type" :type="action.range_type" class-name="text-xs" />
                                {{ formatRange(action.range) }}
                            </span>
                        </span>
                        <span class="w-8 text-center">
                            <span v-if="action.stat != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ action.stat }}<GameIcon v-for="s in splitSuits(action.stat_suits)" :key="s" :type="s" class-name="text-[11px]" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-7 text-center text-white/60">{{ action.resisted_by ?? '-' }}</span>
                        <span class="w-8 text-center">
                            <span v-if="action.target_number != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ action.target_number }}<GameIcon v-for="s in splitSuits(action.target_suits)" :key="s" :type="s" class-name="text-[11px]" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-8 text-center font-medium text-red-400">{{ action.damage ?? '-' }}</span>
                    </div>

                    <!-- Description -->
                    <div v-if="action.description" class="px-1.5 pb-1 text-white/80" :class="contentScale === 'scale-sm' ? 'text-[10px] leading-[15px]' : contentScale === 'scale-md' ? 'text-[11px] leading-4' : 'text-xs leading-5'">
                        <GameText :text="action.description" icon-class="h-3 inline-block align-text-bottom" />
                    </div>

                    <!-- Triggers -->
                    <div v-if="action.triggers.length" class="space-y-0.5 border-t border-white/10 px-1.5 py-1" :class="contentScale === 'scale-sm' ? 'text-[10px] leading-[15px]' : contentScale === 'scale-md' ? 'text-[11px] leading-4' : 'text-xs leading-5'">
                        <div v-for="trigger in action.triggers" :key="trigger.name">
                            <span class="font-bold">
                                <GameIcon v-for="s in splitSuits(trigger.suits)" :key="s" :type="s" class-name="text-xs" />
                                <template v-for="n in trigger.stone_cost" :key="'tsc-' + n">
                                    <GameIcon type="soulstone" class-name="text-xs" />
                                </template>
                                {{ trigger.name }}:
                            </span>
                            <span class="text-white/80">
                                <GameText v-if="trigger.description" :text="trigger.description" icon-class="h-3 inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Tactical Actions -->
            <template v-if="tacticalActions.length">
                <!-- Section header row -->
                <div class="mb-0.5 flex items-center px-1.5 text-white/40" :class="[contentScale === 'scale-sm' ? 'text-[9px]' : 'text-[10px]', attackActions.length ? 'mt-5 border-t border-white/15 pt-3' : '']">
                    <span class="flex-1 font-semibold uppercase tracking-wider">Tactical Actions</span>
                    <span class="w-8 text-center">Rg</span>
                    <span class="w-8 text-center">Stat</span>
                    <span class="w-7 text-center">Rst</span>
                    <span class="w-8 text-center">TN</span>
                    <span class="w-8 text-center">Dmg</span>
                </div>

                <div v-for="action in tacticalActions" :key="'tac-' + action.name" class="mb-1.5 rounded" :style="{ background: `hsl(var(${factionVar}) / 0.08)` }">
                    <!-- Stat row -->
                    <div class="flex items-center px-1.5 py-1" :class="contentScale === 'scale-sm' ? 'text-[11px]' : contentScale === 'scale-md' ? 'text-xs' : 'text-sm'">
                        <div class="flex min-w-0 flex-1 items-center gap-0.5 font-bold">
                            <GameIcon v-if="action.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                            <template v-for="n in action.stone_cost" :key="'sc-' + n">
                                <GameIcon type="soulstone" class-name="text-sm shrink-0" />
                            </template>
                            <span class="truncate">{{ action.name }}</span>
                        </div>
                        <span class="w-8 text-center">
                            <span class="inline-flex items-center justify-center gap-0.5">
                                <GameIcon v-if="action.range_type" :type="action.range_type" class-name="text-xs" />
                                {{ formatRange(action.range) }}
                            </span>
                        </span>
                        <span class="w-8 text-center">
                            <span v-if="action.stat != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ action.stat }}<GameIcon v-for="s in splitSuits(action.stat_suits)" :key="s" :type="s" class-name="text-[11px]" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-7 text-center text-white/60">{{ action.resisted_by ?? '-' }}</span>
                        <span class="w-8 text-center">
                            <span v-if="action.target_number != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ action.target_number }}<GameIcon v-for="s in splitSuits(action.target_suits)" :key="s" :type="s" class-name="text-[11px]" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-8 text-center font-medium text-red-400">{{ action.damage ?? '-' }}</span>
                    </div>

                    <!-- Description -->
                    <div v-if="action.description" class="px-1.5 pb-1 text-white/80" :class="contentScale === 'scale-sm' ? 'text-[10px] leading-[15px]' : contentScale === 'scale-md' ? 'text-[11px] leading-4' : 'text-xs leading-5'">
                        <GameText :text="action.description" icon-class="h-3 inline-block align-text-bottom" />
                    </div>

                    <!-- Triggers -->
                    <div v-if="action.triggers.length" class="space-y-0.5 border-t border-white/10 px-1.5 py-1" :class="contentScale === 'scale-sm' ? 'text-[10px] leading-[15px]' : contentScale === 'scale-md' ? 'text-[11px] leading-4' : 'text-xs leading-5'">
                        <div v-for="trigger in action.triggers" :key="trigger.name">
                            <span class="font-bold">
                                <GameIcon v-for="s in splitSuits(trigger.suits)" :key="s" :type="s" class-name="text-xs" />
                                <template v-for="n in trigger.stone_cost" :key="'tsc-' + n">
                                    <GameIcon type="soulstone" class-name="text-xs" />
                                </template>
                                {{ trigger.name }}:
                            </span>
                            <span class="text-white/80">
                                <GameText v-if="trigger.description" :text="trigger.description" icon-class="h-3 inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer watermark -->
        <div class="px-3 py-1 text-center text-[9px] uppercase tracking-widest text-white/20">Fan-Made Custom Card</div>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: borderGradient }" />
    </div>
</template>
