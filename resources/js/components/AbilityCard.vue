<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Users } from 'lucide-vue-next';

interface AbilityData {
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
    characters_count?: number;
}

defineProps<{
    ability: AbilityData;
}>();
</script>

<template>
    <Card class="flex flex-col">
        <CardHeader class="pb-2">
            <CardTitle class="inline-flex flex-wrap items-center gap-1 text-base">
                <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="h-4 inline-block shrink-0" />
                {{ ability.name }}
                <span
                    v-if="(ability.suits && ability.suits !== 'soulstone') || ability.defensive_ability_type"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                >
                    (<GameIcon v-if="ability.suits && ability.suits !== 'soulstone'" :type="ability.suits" class-name="h-4 inline-block" /><template
                        v-if="ability.defensive_ability_type"
                        ><template v-if="ability.suits && ability.suits !== 'soulstone'">, </template>
                        <GameIcon :type="ability.defensive_ability_type" class-name="h-3.5 inline-block" /></template
                    >)
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col">
            <div v-if="ability.description" class="space-y-1.5 text-sm">
                <p class="text-xs text-muted-foreground">
                    <GameText :text="ability.description" :max-length="120" icon-class="h-4 inline-block align-text-bottom" />
                </p>
            </div>
        </CardContent>
        <div class="mt-auto flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
            <Users class="h-3 w-3 text-muted-foreground" />
            <slot name="footer">
                <span v-if="(ability.characters_count ?? 0) > 0" class="text-muted-foreground">
                    {{ ability.characters_count }} {{ ability.characters_count === 1 ? 'character' : 'characters' }}
                </span>
                <span v-else class="text-muted-foreground">0 characters</span>
            </slot>
        </div>
    </Card>
</template>
