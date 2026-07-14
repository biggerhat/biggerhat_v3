<script setup lang="ts">
import { formatRange, splitSuits } from '@/components/CardCreator/utils';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
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
    source_id?: number | null;
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id?: number | null;
}

const props = defineProps<{
    name: string;
    body: string | null;
    abilities: AbilityData[];
    actions: ActionData[];
}>();

// A Crew Card is shared catalog content any crew can hold (its starter
// and/or a Tier-4 borrow), so this face is always neutrally themed — a
// crew's own faction/Leader context is layered on live around the rendered
// image at display time instead (Arsenal Sheet, Game Tracker), not baked in
// here.
const nameFontSize = computed(() => {
    const len = props.name.length;
    if (len > 32) return 'text-base';
    if (len > 24) return 'text-lg';
    return 'text-xl';
});

// Auto-scale font size based on total content volume — a Crew Card merges
// body text + abilities + actions onto one face (no front/back split, unlike
// a full Leader/Totem stat card), so it fills up faster.
const contentScale = computed(() => {
    const bodyChars = props.body?.length ?? 0;
    const abilityChars = props.abilities.reduce((sum, a) => sum + (a.description?.length ?? 0) + a.name.length, 0);
    const actionChars = props.actions.reduce((sum, a) => {
        const triggerChars = a.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0);
        return sum + (a.description?.length ?? 0) + a.name.length + triggerChars;
    }, 0);
    const total = bodyChars + abilityChars + actionChars;
    if (total > 1800) return 'scale-sm';
    if (total > 1200) return 'scale-md';
    if (total > 700) return 'scale-lg';
    return 'scale-xl';
});
</script>

<template>
    <div class="card-face card-crew relative flex h-full w-full flex-col overflow-hidden rounded-lg bg-neutral-900 text-white">
        <!-- Border -->
        <div class="h-1.5 w-full" style="background: hsl(var(--primary))" />

        <!-- Header -->
        <div class="flex items-center gap-2 px-3 py-2.5" style="background: rgba(255, 255, 255, 0.06)">
            <div class="min-w-0 flex-1">
                <div class="font-bold leading-snug" :class="nameFontSize">{{ name }}</div>
                <div class="mt-0.5 text-[11px] uppercase tracking-wider text-white/60">Crew Card</div>
            </div>
        </div>

        <!-- Body text -->
        <div
            v-if="body"
            class="px-3 py-2 text-white/85"
            :class="contentScale === 'scale-sm' ? 'text-xs leading-5' : contentScale === 'scale-md' ? 'text-sm leading-5' : 'text-base leading-6'"
            style="background: rgba(255, 255, 255, 0.03)"
        >
            <GameText :text="body" icon-class="h-3.5 inline-block align-text-bottom" />
        </div>

        <!-- Abilities -->
        <div v-if="abilities.length" class="px-2.5 py-2" :class="contentScale === 'scale-sm' ? 'text-xs leading-5' : 'text-sm leading-5'">
            <div v-for="ability in abilities" :key="ability.name" class="mb-1.5 last:mb-0">
                <span class="font-bold">
                    <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="text-sm" />
                    <GameIcon
                        v-if="ability.defensive_ability_type && ability.defensive_ability_type !== 'none'"
                        :type="ability.defensive_ability_type"
                        class-name="text-sm"
                    />
                    {{ ability.name }}
                    <GameIcon v-if="ability.suits && ability.suits !== 'none'" :type="ability.suits" class-name="text-sm" />:
                </span>
                <span v-if="ability.description" class="text-white/80">
                    <GameText :text="ability.description" icon-class="h-3 inline-block align-text-bottom" />
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex-1 overflow-hidden px-2.5 py-2">
            <div v-for="action in actions" :key="action.name" class="mb-1.5 rounded" style="background: rgba(255, 255, 255, 0.04)">
                <div
                    class="flex items-center px-1.5 py-1"
                    :class="contentScale === 'scale-sm' ? 'text-[11px]' : contentScale === 'scale-md' ? 'text-xs' : 'text-sm'"
                >
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
                            {{ action.target_number
                            }}<GameIcon v-for="s in splitSuits(action.target_suits)" :key="s" :type="s" class-name="text-[11px]" />
                        </span>
                        <span v-else>-</span>
                    </span>
                    <span class="w-8 text-center font-medium text-red-400">{{ action.damage ?? '-' }}</span>
                </div>

                <div
                    v-if="action.description"
                    class="px-1.5 pb-1 text-white/80"
                    :class="
                        contentScale === 'scale-sm'
                            ? 'text-[10px] leading-[15px]'
                            : contentScale === 'scale-md'
                              ? 'text-[11px] leading-4'
                              : 'text-xs leading-5'
                    "
                >
                    <GameText :text="action.description" icon-class="h-3 inline-block align-text-bottom" />
                </div>

                <div
                    v-if="action.triggers.length"
                    class="space-y-0.5 border-t border-white/10 px-1.5 py-1"
                    :class="
                        contentScale === 'scale-sm'
                            ? 'text-[10px] leading-[15px]'
                            : contentScale === 'scale-md'
                              ? 'text-[11px] leading-4'
                              : 'text-xs leading-5'
                    "
                >
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
        </div>

        <!-- Border -->
        <div class="h-1 w-full" style="background: hsl(var(--primary))" />
    </div>
</template>
