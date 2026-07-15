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

// Tarot proportions (matches Leader/Totem/single-catalog-row cards): 550x950.
// A combined card holds the starter effect plus every Tier-4 borrow, so its
// content is unbounded — rather than shrinking text to cram it into a fixed
// 550x950 box (which either clips or becomes unreadable), the CARD ITSELF
// grows through discrete tiers, always keeping the tarot aspect ratio, while
// text stays one fixed, comfortably large size throughout.
const TAROT_RATIO = 950 / 550;
const WIDTH_TIERS = [550, 650, 750, 850, 950, 1050, 1150];

const totalContentChars = computed(() => {
    return props.items.reduce((sum, item) => {
        const d = item.data as ActionData & AbilityData;
        const qualifierChars = item.qualifier?.length ?? 0;
        const triggerChars = 'triggers' in d ? d.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0) : 0;
        return sum + qualifierChars + (d.description?.length ?? 0) + d.name.length + triggerChars;
    }, 0);
});

const cardWidth = computed(() => {
    const tierIndex = Math.min(Math.floor(totalContentChars.value / 900), WIDTH_TIERS.length - 1);
    return WIDTH_TIERS[tierIndex];
});
const cardHeight = computed(() => Math.round(cardWidth.value * TAROT_RATIO));

const isAction = (item: CombinedItem): item is CombinedItem & { data: ActionData } => item.type === 'action';
const isAbility = (item: CombinedItem): item is CombinedItem & { data: AbilityData } => item.type === 'ability';
const isTrigger = (item: CombinedItem): item is CombinedItem & { data: TriggerData } => item.type === 'trigger';
</script>

<template>
    <!-- No overflow-hidden here — the tarot-tiered size above is a target,
         not a hard cap. If a real crew's content ever outgrows the largest
         tier, the box grows past it rather than silently clipping content;
         the rounded corners on the border strips below make that safe. -->
    <div
        class="card-face card-crew relative flex flex-col bg-neutral-900 text-white"
        :style="{ width: cardWidth + 'px', minHeight: cardHeight + 'px' }"
    >
        <!-- Border -->
        <div class="h-1.5 w-full rounded-t-lg" style="background: hsl(var(--primary))" />

        <!-- Header -->
        <div class="flex items-center gap-2 px-3 py-3" style="background: rgba(255, 255, 255, 0.06)">
            <div class="min-w-0 flex-1">
                <div class="text-2xl font-bold leading-snug">{{ crewName }}</div>
                <div class="mt-0.5 text-xs uppercase tracking-wider text-white/60">Crew Card</div>
            </div>
        </div>

        <!-- Effects -->
        <div class="flex-1 px-3 py-2.5 text-sm leading-6">
            <div v-for="(item, idx) in items" :key="idx" class="mb-2 rounded" style="background: rgba(255, 255, 255, 0.04)">
                <p v-if="item.qualifier" class="px-2 pt-1.5 text-xs font-semibold uppercase italic tracking-wide text-white/60">
                    {{ item.qualifier }}
                </p>

                <!-- Ability -->
                <div v-if="isAbility(item)" class="px-2 py-1.5">
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
                        <GameText :text="item.data.description" icon-class="h-3.5 inline-block align-text-bottom" />
                    </span>
                </div>

                <!-- Standalone trigger -->
                <div v-else-if="isTrigger(item)" class="px-2 py-1.5">
                    <span class="font-bold">
                        <GameIcon v-for="s in splitSuits(item.data.suits)" :key="s" :type="s" class-name="text-sm" />
                        <template v-for="n in item.data.stone_cost" :key="'tsc-' + n">
                            <GameIcon type="soulstone" class-name="text-sm" />
                        </template>
                        {{ item.data.name }}:
                    </span>
                    <span v-if="item.data.description" class="text-white/80">
                        <GameText :text="item.data.description" icon-class="h-3.5 inline-block align-text-bottom" />
                    </span>
                </div>

                <!-- Action -->
                <template v-else-if="isAction(item)">
                    <div class="flex items-center px-2 py-1.5">
                        <div class="flex min-w-0 flex-1 items-center gap-1 font-bold">
                            <GameIcon v-if="item.data.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                            <template v-for="n in item.data.stone_cost" :key="'sc-' + n">
                                <GameIcon type="soulstone" class-name="text-sm shrink-0" />
                            </template>
                            <span class="truncate">{{ item.data.name }}</span>
                        </div>
                        <span class="w-9 text-center">
                            <span class="inline-flex items-center justify-center gap-0.5">
                                <GameIcon v-if="item.data.range_type" :type="item.data.range_type" class-name="text-xs" />
                                {{ formatRange(item.data.range) }}
                            </span>
                        </span>
                        <span class="w-9 text-center">
                            <span v-if="item.data.stat != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ item.data.stat }}<GameIcon v-for="s in splitSuits(item.data.stat_suits)" :key="s" :type="s" class-name="text-xs" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-8 text-center text-white/60">{{ item.data.resisted_by ?? '-' }}</span>
                        <span class="w-9 text-center">
                            <span v-if="item.data.target_number != null" class="inline-flex items-center justify-center gap-0.5">
                                {{ item.data.target_number
                                }}<GameIcon v-for="s in splitSuits(item.data.target_suits)" :key="s" :type="s" class-name="text-xs" />
                            </span>
                            <span v-else>-</span>
                        </span>
                        <span class="w-9 text-center font-medium text-red-400">{{ item.data.damage ?? '-' }}</span>
                    </div>

                    <div v-if="item.data.description" class="px-2 pb-1.5 text-white/80">
                        <GameText :text="item.data.description" icon-class="h-3.5 inline-block align-text-bottom" />
                    </div>

                    <div v-if="item.data.triggers.length" class="space-y-1 border-t border-white/10 px-2 py-1.5">
                        <div v-for="trigger in item.data.triggers" :key="trigger.name">
                            <span class="font-bold">
                                <GameIcon v-for="s in splitSuits(trigger.suits)" :key="s" :type="s" class-name="text-sm" />
                                <template v-for="n in trigger.stone_cost" :key="'tsc-' + n">
                                    <GameIcon type="soulstone" class-name="text-sm" />
                                </template>
                                {{ trigger.name }}:
                            </span>
                            <span class="text-white/80">
                                <GameText v-if="trigger.description" :text="trigger.description" icon-class="h-3.5 inline-block align-text-bottom" />
                            </span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Border -->
        <div class="h-1 w-full rounded-b-lg" style="background: hsl(var(--primary))" />
    </div>
</template>
