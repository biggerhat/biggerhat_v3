<script setup lang="ts">
import { formatRange, getFactionVar, splitSuits } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { computed } from 'vue';

interface ContentBlock {
    type: 'text' | 'ability' | 'action' | 'trigger';
    text?: string;
    data?: Record<string, any>;
}

const props = defineProps<{
    name: string;
    domain: string;
    faction: string | null;
    upgradeType: string | null;
    upgradeTypeLabel: string | null;
    limitations: string | null;
    limitationsLabel: string | null;
    masterName: string | null;
    keywordName: string | null;
    contentBlocks: ContentBlock[];
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
    const total = props.contentBlocks.reduce((sum, b) => {
        if (b.type === 'text') return sum + (b.text?.length ?? 0);
        return sum + (b.data?.name?.length ?? 0) + (b.data?.description?.length ?? 0);
    }, 0);
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

const statTextSize = computed(() => {
    if (contentScale.value === 'scale-sm') return 'text-[11px]';
    if (contentScale.value === 'scale-md') return 'text-xs';
    return 'text-sm';
});

const headerTextSize = computed(() => {
    if (contentScale.value === 'scale-sm') return 'text-[9px]';
    return 'text-[10px]';
});
</script>

<template>
    <div class="card-face card-front relative flex h-full w-full flex-col overflow-hidden rounded-lg bg-neutral-900 text-white">
        <!-- Faction border top -->
        <div class="h-1.5 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />

        <!-- Type label (character upgrades) -->
        <div
            v-if="!isCrew && upgradeTypeLabel"
            class="px-3 py-1 text-center text-[11px] font-bold uppercase tracking-widest"
            :style="{ background: `hsl(var(${factionVar}) / 0.2)`, color: `hsl(var(${factionVar}))` }"
        >
            {{ upgradeTypeLabel }}
        </div>

        <!-- Header -->
        <div class="px-3 py-2" :style="{ background: `hsl(var(${factionVar}) / 0.15)` }">
            <div class="flex items-center gap-2">
                <FactionLogo v-if="faction" :faction="faction" class-name="size-5 shrink-0" />
                <div class="min-w-0 flex-1 text-center">
                    <div class="font-bold uppercase tracking-wide" :class="nameFontSize">{{ name || 'Upgrade Name' }}</div>
                    <div v-if="isCrew && masterName" class="text-xs text-white/60">{{ masterName }}</div>
                </div>
                <div v-if="faction" class="size-5 shrink-0" />
            </div>
        </div>

        <div class="h-px" :style="{ background: `hsl(var(${factionVar}) / 0.4)` }" />

        <!-- Content blocks -->
        <div class="flex-1 overflow-hidden px-2.5 py-2">
            <template v-for="(block, idx) in contentBlocks" :key="'cb-' + idx">
                <!-- Text preface -->
                <div v-if="block.type === 'text' && block.text" class="mb-1 font-semibold italic text-white/50" :class="textSize">
                    {{ block.text }}
                </div>

                <!-- Ability -->
                <div v-else-if="block.type === 'ability' && block.data" class="mb-1.5" :class="textSize">
                    <span class="font-bold">
                        <GameIcon v-if="block.data.costs_stone" type="soulstone" class-name="text-sm" />
                        <GameIcon
                            v-if="block.data.defensive_ability_type && block.data.defensive_ability_type !== 'none'"
                            :type="block.data.defensive_ability_type"
                            class-name="text-sm"
                        />
                        {{ block.data.name }}
                        <GameIcon v-if="block.data.suits && block.data.suits !== 'none'" :type="block.data.suits" class-name="text-sm" />:
                    </span>
                    <span v-if="block.data.description" class="text-white/80">
                        <GameText :text="block.data.description" icon-class="h-3 inline-block align-text-bottom" />
                    </span>
                </div>

                <!-- Action -->
                <div
                    v-else-if="block.type === 'action' && block.data"
                    class="mb-1.5 rounded"
                    :style="{ background: `hsl(var(${factionVar}) / 0.08)` }"
                >
                    <!-- Header row -->
                    <div class="mb-0.5 flex items-center px-1.5 text-white/40" :class="headerTextSize">
                        <span class="flex-1 font-semibold uppercase tracking-wider">{{
                            block.data.type === 'tactical' ? 'Tactical Action' : 'Attack Action'
                        }}</span>
                        <span class="w-8 text-center">Rg</span>
                        <span class="w-8 text-center">Stat</span>
                        <span class="w-7 text-center">Rst</span>
                        <span class="w-8 text-center">TN</span>
                        <span class="w-8 text-center">Dmg</span>
                    </div>
                    <!-- Stat row -->
                    <div class="flex items-center px-1.5 py-1" :class="statTextSize">
                        <div class="flex min-w-0 flex-1 items-center gap-0.5 font-bold">
                            <GameIcon v-if="block.data.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                            <template v-for="n in block.data.stone_cost" :key="'sc-' + n"
                                ><GameIcon type="soulstone" class-name="text-sm shrink-0"
                            /></template>
                            <span class="truncate">{{ block.data.name }}</span>
                        </div>
                        <span class="w-8 text-center"
                            ><span class="inline-flex items-center justify-center gap-0.5"
                                ><GameIcon v-if="block.data.range_type" :type="block.data.range_type" class-name="text-xs" />{{
                                    formatRange(block.data.range as number | string | null | undefined)
                                }}</span
                            ></span
                        >
                        <span class="w-8 text-center"
                            ><span v-if="block.data.stat != null" class="inline-flex items-center justify-center gap-0.5"
                                >{{ block.data.stat
                                }}<GameIcon v-for="s in splitSuits(block.data.stat_suits)" :key="s" :type="s" class-name="text-xs" /></span
                            ><span v-else>-</span></span
                        >
                        <span class="w-7 text-center text-white/60">{{ block.data.resisted_by ?? '-' }}</span>
                        <span class="w-8 text-center"
                            ><span v-if="block.data.target_number != null" class="inline-flex items-center justify-center gap-0.5"
                                >{{ block.data.target_number
                                }}<GameIcon v-for="s in splitSuits(block.data.target_suits)" :key="s" :type="s" class-name="text-xs" /></span
                            ><span v-else>-</span></span
                        >
                        <span class="w-8 text-center font-medium text-red-400">{{ block.data.damage ?? '-' }}</span>
                    </div>
                    <div v-if="block.data.description" class="px-1.5 pb-1 text-white/80" :class="textSize">
                        <GameText :text="block.data.description" icon-class="h-3 inline-block align-text-bottom" />
                    </div>
                    <!-- Triggers within action -->
                    <div v-if="block.data.triggers?.length" class="space-y-0.5 border-t border-white/10 px-1.5 py-1" :class="textSize">
                        <div v-for="trigger in block.data.triggers" :key="trigger.name">
                            <span class="font-bold"
                                ><GameIcon v-for="s in splitSuits(trigger.suits)" :key="s" :type="s" class-name="text-xs" /> {{ trigger.name }}:</span
                            >
                            <span class="text-white/80"
                                ><GameText v-if="trigger.description" :text="trigger.description" icon-class="h-3 inline-block align-text-bottom"
                            /></span>
                        </div>
                    </div>
                </div>

                <!-- Standalone trigger -->
                <div v-else-if="block.type === 'trigger' && block.data" class="mb-1.5" :class="textSize">
                    <span class="font-bold">
                        <GameIcon v-for="s in splitSuits(block.data.suits)" :key="s" :type="s" class-name="text-xs" />
                        <template v-for="n in block.data.stone_cost" :key="'tsc-' + n"><GameIcon type="soulstone" class-name="text-xs" /></template>
                        {{ block.data.name }}:
                    </span>
                    <span v-if="block.data.description" class="text-white/80">
                        <GameText :text="block.data.description" icon-class="h-3 inline-block align-text-bottom" />
                    </span>
                </div>
            </template>
        </div>

        <!-- Limitations (character upgrades) -->
        <div
            v-if="!isCrew && limitationsLabel"
            class="mx-2.5 mb-2 rounded border border-white/20 px-2 py-1 text-center text-[11px] font-semibold uppercase tracking-wider text-white/60"
        >
            Limitations: {{ limitationsLabel }}
        </div>

        <!-- Footer -->
        <div
            class="px-3 py-1.5 text-center text-[10px] font-semibold uppercase tracking-widest text-white/30"
            :style="{ background: `hsl(var(${factionVar}) / 0.1)` }"
        >
            {{ isCrew ? 'Crew Card' : 'Upgrade' }}
        </div>

        <!-- Faction border bottom -->
        <div class="h-1 w-full" :style="{ background: `hsl(var(${factionVar}))` }" />
    </div>
</template>
