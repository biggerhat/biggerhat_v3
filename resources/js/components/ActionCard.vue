<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { ScrollText, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Trigger {
    id?: number;
    name: string;
    suits?: string;
    stone_cost?: number;
    description?: string;
}

interface ActionCharacter {
    display_name: string;
    slug: string;
    faction: string | null;
    standard_miniatures?: { id: number; slug: string }[];
}

interface ActionUpgrade {
    name: string;
    slug: string;
}

interface ActionData {
    name: string;
    action_type?: string;
    type?: string;
    is_signature?: boolean;
    stone_cost?: number;
    range_type?: string;
    range?: number | string | null;
    stat?: number | string | null;
    stat_suits?: string | null;
    stat_modifier?: string | null;
    resisted_by?: string | null;
    target_number?: number | string | null;
    target_suits?: string | null;
    damage?: string | null;
    description?: string | null;
    triggers?: Trigger[];
    characters_count?: number;
    characters?: ActionCharacter[];
    upgrades?: ActionUpgrade[];
}

const props = withDefaults(
    defineProps<{
        action: ActionData;
        forLootCard?: boolean;
        hideFooter?: boolean;
    }>(),
    { hideFooter: false, forLootCard: false },
);

const showUpgradeIcon = computed(() => (props.action.characters_count ?? 0) === 0 && (props.action.upgrades?.length ?? 0) > 0);

const formatActionType = (type?: string) => {
    if (!type) return '';
    return type.charAt(0).toUpperCase() + type.slice(1);
};
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <div :class="['flex items-center border-b bg-secondary px-3 font-semibold', forLootCard ? 'py-px text-[8px]' : 'py-1.5 text-xs']">
            <span class="flex-1">{{ formatActionType(action.action_type ?? action.type) }} Action</span>
            <span :class="['text-center text-muted-foreground', forLootCard ? 'w-5' : 'w-10']">Rg</span>
            <span :class="['text-center text-muted-foreground', forLootCard ? 'w-5' : 'w-10']">Stat</span>
            <span :class="['text-center text-muted-foreground', forLootCard ? 'w-5' : 'w-10']">Rst</span>
            <span :class="['text-center text-muted-foreground', forLootCard ? 'w-5' : 'w-10']">TN</span>
            <span :class="['text-center text-muted-foreground', forLootCard ? 'w-5' : 'w-10']">Dmg</span>
        </div>
        <div :class="['flex items-center border-b px-3', forLootCard ? 'py-0.5' : 'py-2']">
            <div class="inline-flex min-w-0 flex-1 items-center gap-1">
                <GameIcon v-if="action.is_signature" type="signature_action" :class-name="forLootCard ? 'text-[9px] inline-block shrink-0' : 'h-4 inline-block shrink-0'" />
                <GameIcon v-for="n in action.stone_cost ?? 0" :key="n" type="soulstone" :class-name="forLootCard ? 'text-[9px] inline-block shrink-0' : 'h-4 inline-block shrink-0'" />
                <span class="font-semibold">{{ action.name }}</span>
            </div>
            <span :class="['text-center', forLootCard ? 'w-5 text-[8px]' : 'w-10 text-sm']">
                <span class="inline-flex items-center justify-center gap-0.5">
                    <GameIcon v-if="action.range_type" :type="action.range_type" :class-name="forLootCard ? 'text-[9px] inline-block' : 'h-3.5 inline-block'" />
                    {{ action.range != null ? action.range + '"' : '-' }}
                </span>
            </span>
            <span :class="['text-center', forLootCard ? 'w-5 text-[8px]' : 'w-10 text-sm']">
                <template v-if="action.stat != null">
                    <span class="inline-flex items-center justify-center gap-0.5">
                        {{ action.stat }}
                        <GameIcon v-if="action.stat_modifier" :type="action.stat_modifier" :class-name="forLootCard ? 'text-[9px] inline-block' : 'h-4.5 inline-block'" />
                        <template v-if="action.stat_suits">
                            <GameIcon v-for="suit in action.stat_suits.split(' ')" :key="suit" :type="suit" :class-name="forLootCard ? 'text-[9px] inline-block' : 'h-3.5 inline-block'" />
                        </template>
                    </span>
                </template>
                <template v-else>-</template>
            </span>
            <span :class="['text-center', forLootCard ? 'w-5 text-[8px]' : 'w-10 text-sm']">{{ action.resisted_by ?? '-' }}</span>
            <span :class="['text-center', forLootCard ? 'w-5 text-[8px]' : 'w-10 text-sm']">
                <template v-if="action.target_number != null">
                    <span class="inline-flex items-center justify-center gap-0.5">
                        {{ action.target_number }}
                        <template v-if="action.target_suits">
                            <GameIcon v-for="suit in action.target_suits.split(' ')" :key="suit" :type="suit" :class-name="forLootCard ? 'text-[9px] inline-block' : 'h-3.5 inline-block'" />
                        </template>
                    </span>
                </template>
                <template v-else>-</template>
            </span>
            <span :class="['text-center', forLootCard ? 'w-5 text-[8px]' : 'w-10 text-sm']">{{ action.damage ?? '-' }}</span>
        </div>
        <div v-if="action.description" :class="['px-3', forLootCard ? 'py-0.5' : 'py-2']">
            <p :class="['leading-relaxed text-muted-foreground', forLootCard ? 'text-[8px]' : 'text-xs']">
                <GameText :text="action.description" :icon-class="forLootCard ? 'text-[9px] inline-block align-text-bottom' : 'h-4 inline-block align-text-bottom'" />
            </p>
        </div>
        <div v-if="action.triggers?.length" :class="['space-y-0.5 border-t px-3', forLootCard ? 'py-0.5' : 'py-2']">
            <div v-for="(trigger, tidx) in action.triggers" :key="tidx" :class="['leading-relaxed text-muted-foreground', forLootCard ? 'text-[8px]' : 'text-xs']">
                <span class="inline-flex items-center gap-0.5 font-semibold text-foreground">
                    <GameIcon v-if="trigger.suits" :type="trigger.suits" :class-name="forLootCard ? 'text-[9px] inline-block' : 'h-3.5 inline-block'" />
                    <GameIcon v-for="n in trigger.stone_cost ?? 0" :key="n" type="soulstone" :class-name="forLootCard ? 'text-[9px] inline-block' : 'h-3.5 inline-block'" />
                    {{ trigger.name }}:
                </span>
                {{ ' ' }}
                <GameText v-if="trigger.description" :text="trigger.description" :max-length="120" :icon-class="forLootCard ? 'text-[9px] inline-block align-text-bottom' : 'h-4 inline-block align-text-bottom'" />
            </div>
        </div>
        <div v-if="!hideFooter" class="mt-auto flex flex-wrap items-center gap-1.5 border-t px-3 py-1.5 text-xs">
            <ScrollText v-if="showUpgradeIcon" class="h-3 w-3 shrink-0 text-muted-foreground" />
            <Users v-else class="h-3 w-3 shrink-0 text-muted-foreground" />
            <slot name="footer">
                <template v-if="action.characters_count === 1 && action.characters?.length === 1">
                    <span class="text-muted-foreground">{{ action.characters[0].display_name }}</span>
                </template>
                <template v-else-if="(action.characters_count ?? 0) > 1">
                    <span class="text-muted-foreground">{{ action.characters_count }} characters</span>
                </template>
                <template v-else-if="action.upgrades?.length">
                    <Badge v-for="upgrade in action.upgrades" :key="upgrade.slug" variant="secondary" class="text-[10px]">
                        {{ upgrade.name }}
                    </Badge>
                </template>
                <span v-else class="text-muted-foreground">0 characters</span>
            </slot>
        </div>
    </Card>
</template>
