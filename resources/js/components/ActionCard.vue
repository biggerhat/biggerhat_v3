<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Card } from '@/components/ui/card';
import { Users } from 'lucide-vue-next';

interface Trigger {
    id?: number;
    name: string;
    suits?: string;
    costs_stone?: boolean;
    description?: string;
}

interface ActionData {
    name: string;
    action_type?: string;
    type?: string;
    is_signature?: boolean;
    costs_stone?: boolean;
    range_type?: string;
    range?: number | string | null;
    stat?: number | string | null;
    stat_suits?: string | null;
    resisted_by?: string | null;
    target_number?: number | string | null;
    target_suits?: string | null;
    damage?: string | null;
    description?: string | null;
    triggers?: Trigger[];
    characters_count?: number;
}

defineProps<{
    action: ActionData;
}>();

const formatActionType = (type?: string) => {
    if (!type) return '';
    return type.charAt(0).toUpperCase() + type.slice(1);
};
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <div class="flex items-center border-b bg-secondary px-3 py-1.5 text-xs font-semibold">
            <span class="flex-1">{{ formatActionType(action.action_type ?? action.type) }} Action</span>
            <span class="w-10 text-center text-muted-foreground">Rg</span>
            <span class="w-10 text-center text-muted-foreground">Stat</span>
            <span class="w-10 text-center text-muted-foreground">Rst</span>
            <span class="w-10 text-center text-muted-foreground">TN</span>
            <span class="w-10 text-center text-muted-foreground">Dmg</span>
        </div>
        <div class="flex items-center border-b px-3 py-2">
            <div class="inline-flex min-w-0 flex-1 items-center gap-1">
                <GameIcon v-if="action.is_signature" type="signature_action" class-name="h-4 inline-block shrink-0" />
                <GameIcon v-if="action.costs_stone" type="soulstone" class-name="h-4 inline-block shrink-0" />
                <span class="font-semibold">{{ action.name }}</span>
            </div>
            <span class="w-10 text-center text-sm">
                <span class="inline-flex items-center justify-center gap-0.5">
                    <GameIcon v-if="action.range_type" :type="action.range_type" class-name="h-3.5 inline-block" />
                    {{ action.range != null ? action.range + '"' : '-' }}
                </span>
            </span>
            <span class="w-10 text-center text-sm">
                <template v-if="action.stat != null">
                    <span class="inline-flex items-center justify-center gap-0.5">
                        {{ action.stat }}
                        <template v-if="action.stat_suits">
                            <GameIcon v-for="suit in action.stat_suits.split(' ')" :key="suit" :type="suit" class-name="h-3.5 inline-block" />
                        </template>
                    </span>
                </template>
                <template v-else>-</template>
            </span>
            <span class="w-10 text-center text-sm">{{ action.resisted_by ?? '-' }}</span>
            <span class="w-10 text-center text-sm">
                <template v-if="action.target_number != null">
                    <span class="inline-flex items-center justify-center gap-0.5">
                        {{ action.target_number }}
                        <template v-if="action.target_suits">
                            <GameIcon v-for="suit in action.target_suits.split(' ')" :key="suit" :type="suit" class-name="h-3.5 inline-block" />
                        </template>
                    </span>
                </template>
                <template v-else>-</template>
            </span>
            <span class="w-10 text-center text-sm">{{ action.damage ?? '-' }}</span>
        </div>
        <div v-if="action.description" class="px-3 py-2">
            <p class="text-xs leading-relaxed text-muted-foreground">
                <GameText :text="action.description" :max-length="150" icon-class="h-4 inline-block align-text-bottom" />
            </p>
        </div>
        <div v-if="action.triggers?.length" class="space-y-1 border-t px-3 py-2">
            <div v-for="(trigger, tidx) in action.triggers" :key="tidx" class="text-xs leading-relaxed text-muted-foreground">
                <span class="inline-flex items-center gap-0.5 font-semibold text-foreground">
                    <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="h-3.5 inline-block" />
                    <GameIcon v-if="trigger.costs_stone" type="soulstone" class-name="h-3.5 inline-block" />
                    {{ trigger.name }}:
                </span>
                {{ ' ' }}
                <GameText v-if="trigger.description" :text="trigger.description" :max-length="120" icon-class="h-4 inline-block align-text-bottom" />
            </div>
        </div>
        <div class="mt-auto flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
            <Users class="h-3 w-3 text-muted-foreground" />
            <slot name="footer">
                <span v-if="(action.characters_count ?? 0) > 0" class="text-muted-foreground">
                    {{ action.characters_count }} {{ action.characters_count === 1 ? 'character' : 'characters' }}
                </span>
                <span v-else class="text-muted-foreground">0 characters</span>
            </slot>
        </div>
    </Card>
</template>
