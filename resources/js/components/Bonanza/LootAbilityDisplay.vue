<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';

interface AbilityLike {
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
}

defineProps<{
    ability: AbilityLike;
}>();
</script>

<template>
    <div class="rounded-md border border-border/60 bg-muted/40 px-2.5 py-1.5 text-xs">
        <div class="flex flex-wrap items-baseline gap-1 leading-tight">
            <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="h-3.5 inline-block shrink-0 self-center" />
            <span class="font-semibold">{{ ability.name }}</span>
            <span
                v-if="(ability.suits && ability.suits !== 'soulstone') || ability.defensive_ability_type"
                class="inline-flex items-center gap-0.5 text-[10px] text-muted-foreground"
            >
                (<GameIcon v-if="ability.suits && ability.suits !== 'soulstone'" :type="ability.suits" class-name="h-3 inline-block" /><template
                    v-if="ability.defensive_ability_type"
                    ><template v-if="ability.suits && ability.suits !== 'soulstone'">, </template
                    ><GameIcon :type="ability.defensive_ability_type" class-name="h-3 inline-block" /></template
                >)
            </span>
        </div>
        <p v-if="ability.description" class="mt-1 leading-relaxed text-muted-foreground">
            <GameText :text="ability.description" icon-class="h-3.5 inline-block align-text-bottom" />
        </p>
    </div>
</template>
