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

interface CombinedItem {
    type: 'action' | 'ability' | 'trigger';
    // Restriction qualifying text (pg 32, 54) — printed above the effect it
    // gates, e.g. "Friendly Ten Thunders models gain the following action:".
    // Null for the starter effect and any generic-catalog Tier-4 borrow,
    // since only a real Crew Card Upgrade's restriction pivot can produce one.
    qualifier: string | null;
    data: ActionData | AbilityData | TriggerData;
}

const props = defineProps<{
    crewName: string;
    items: CombinedItem[];
}>();

// Auto-scale font size based on total content volume, same reasoning as
// CrewCardFace — this face can hold an arbitrary number of borrowed effects,
// so it fills up far faster than a single catalog row ever did.
const contentScale = computed(() => {
    const total = props.items.reduce((sum, item) => {
        const d = item.data as ActionData & AbilityData;
        const qualifierChars = item.qualifier?.length ?? 0;
        const triggerChars = 'triggers' in d ? d.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0) : 0;
        return sum + qualifierChars + (d.description?.length ?? 0) + d.name.length + triggerChars;
    }, 0);
    if (total > 2600) return 'scale-sm';
    if (total > 1800) return 'scale-md';
    if (total > 1000) return 'scale-lg';
    return 'scale-xl';
});

const isAction = (item: CombinedItem): item is CombinedItem & { data: ActionData } => item.type === 'action';
const isAbility = (item: CombinedItem): item is CombinedItem & { data: AbilityData } => item.type === 'ability';
const isTrigger = (item: CombinedItem): item is CombinedItem & { data: TriggerData } => item.type === 'trigger';
</script>

<template>
    <div class="card-face card-crew relative flex h-full w-full flex-col overflow-hidden rounded-lg bg-neutral-900 text-white">
        <!-- Border -->
        <div class="h-1.5 w-full" style="background: hsl(var(--primary))" />

        <!-- Header -->
        <div class="flex items-center gap-2 px-3 py-2.5" style="background: rgba(255, 255, 255, 0.06)">
            <div class="min-w-0 flex-1">
                <div class="text-xl font-bold leading-snug">{{ crewName }}</div>
                <div class="mt-0.5 text-[11px] uppercase tracking-wider text-white/60">Crew Card</div>
            </div>
        </div>

        <!-- Effects -->
        <div class="flex-1 overflow-hidden px-2.5 py-2" :class="contentScale === 'scale-sm' ? 'text-xs leading-5' : 'text-sm leading-5'">
            <div v-for="(item, idx) in items" :key="idx" class="mb-1.5 rounded" style="background: rgba(255, 255, 255, 0.04)">
                <p
                    v-if="item.qualifier"
                    class="px-1.5 pt-1 text-[10px] font-semibold uppercase italic tracking-wide text-white/60"
                >
                    {{ item.qualifier }}
                </p>

                <!-- Ability -->
                <div v-if="isAbility(item)" class="px-1.5 py-1">
                    <span class="font-bold">
                        <GameIcon v-if="item.data.costs_stone" type="soulstone" class-name="text-sm" />
                        <GameIcon
                            v-if="item.data.defensive_ability_type && item.data.defensive_ability_type !== 'none'"
                            :type="item.data.defensive_ability_type"
                            class-name="text-sm"
                        />
                        {{ item.data.name }}
                        <GameIcon v-if="item.data.suits && item.data.suits !== 'none'" :type="item.data.suits" class-name="text-sm" />:
                    </span>
                    <span v-if="item.data.description" class="text-white/80">
                        <GameText :text="item.data.description" icon-class="h-3 inline-block align-text-bottom" />
                    </span>
                </div>

                <!-- Standalone trigger -->
                <div v-else-if="isTrigger(item)" class="px-1.5 py-1">
                    <span class="font-bold">
                        <GameIcon v-for="s in splitSuits(item.data.suits)" :key="s" :type="s" class-name="text-xs" />
                        <template v-for="n in item.data.stone_cost" :key="'tsc-' + n">
                            <GameIcon type="soulstone" class-name="text-xs" />
                        </template>
                        {{ item.data.name }}:
                    </span>
                    <span v-if="item.data.description" class="text-white/80">
                        <GameText :text="item.data.description" icon-class="h-3 inline-block align-text-bottom" />
                    </span>
                </div>

                <!-- Action -->
                <template v-else-if="isAction(item)">
                    <div
                        class="flex items-center px-1.5 py-1"
                        :class="contentScale === 'scale-sm' ? 'text-[11px]' : contentScale === 'scale-md' ? 'text-xs' : 'text-sm'"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-0.5 font-bold">
                            <GameIcon v-if="item.data.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                            <template v-for="n in item.data.stone_cost" :key="'sc-' + n">
                                <GameIcon type="soulstone" class-name="text-sm shrink-0" />
                            </template>
                            <span class="truncate">{{ item.data.name }}</span>
                        </div>
                        <span class="w-8 text-center">
                            <span class="inline-flex items-center justify-center gap-0.5">
                                <GameIcon v-if="item.data.range_type" :type="item.data.range_type" class-name="text-xs" />
                                {{ formatRange(item.data.range) }}
                            </span>
                        </span>
                        <span class="w-8 text-center">
                            <span v-if="item.data.stat != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ item.data.stat
                                }}<GameIcon v-for="s in splitSuits(item.data.stat_suits)" :key="s" :type="s" class-name="text-[11px]" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-7 text-center text-white/60">{{ item.data.resisted_by ?? '-' }}</span>
                        <span class="w-8 text-center">
                            <span v-if="item.data.target_number != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ item.data.target_number
                                }}<GameIcon v-for="s in splitSuits(item.data.target_suits)" :key="s" :type="s" class-name="text-[11px]" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-8 text-center font-medium text-red-400">{{ item.data.damage ?? '-' }}</span>
                    </div>

                    <div
                        v-if="item.data.description"
                        class="px-1.5 pb-1 text-white/80"
                        :class="
                            contentScale === 'scale-sm'
                                ? 'text-[10px] leading-[15px]'
                                : contentScale === 'scale-md'
                                  ? 'text-[11px] leading-4'
                                  : 'text-xs leading-5'
                        "
                    >
                        <GameText :text="item.data.description" icon-class="h-3 inline-block align-text-bottom" />
                    </div>

                    <div
                        v-if="item.data.triggers.length"
                        class="space-y-0.5 border-t border-white/10 px-1.5 py-1"
                        :class="
                            contentScale === 'scale-sm'
                                ? 'text-[10px] leading-[15px]'
                                : contentScale === 'scale-md'
                                  ? 'text-[11px] leading-4'
                                  : 'text-xs leading-5'
                        "
                    >
                        <div v-for="trigger in item.data.triggers" :key="trigger.name">
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
                </template>
            </div>
        </div>

        <!-- Border -->
        <div class="h-1 w-full" style="background: hsl(var(--primary))" />
    </div>
</template>
