<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Users } from 'lucide-vue-next';

interface AbilityCharacter {
    display_name: string;
    slug: string;
    faction: string | null;
}

interface AbilityUpgrade {
    name: string;
    slug: string;
}

interface AbilityData {
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
    characters_count?: number;
    characters?: AbilityCharacter[];
    upgrades?: AbilityUpgrade[];
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
        <div class="mt-auto flex flex-wrap items-center gap-1.5 border-t px-3 py-1.5 text-xs">
            <Users class="h-3 w-3 shrink-0 text-muted-foreground" />
            <slot name="footer">
                <template v-if="ability.characters_count === 1 && ability.characters?.length === 1">
                    <span class="text-muted-foreground">{{ ability.characters[0].display_name }}</span>
                </template>
                <template v-else-if="(ability.characters_count ?? 0) > 1">
                    <span class="text-muted-foreground">{{ ability.characters_count }} characters</span>
                </template>
                <template v-else-if="ability.upgrades?.length">
                    <Badge v-for="upgrade in ability.upgrades" :key="upgrade.slug" variant="secondary" class="text-[10px]">
                        {{ upgrade.name }}
                    </Badge>
                </template>
                <span v-else class="text-muted-foreground">0 characters</span>
            </slot>
        </div>
    </Card>
</template>
