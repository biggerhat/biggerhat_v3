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
    <div class="rounded-md border border-border/60 bg-muted/40 px-2 py-0.5 text-[8px] text-black">
        <div class="flex flex-wrap items-baseline gap-1 leading-tight">
            <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="text-[9px] inline-block shrink-0 self-center" />
            <span class="font-semibold">{{ ability.name }}</span>
            <span v-if="(ability.suits && ability.suits !== 'soulstone') || ability.defensive_ability_type" class="inline-flex items-center gap-0.5">
                (<GameIcon
                    v-if="ability.suits && ability.suits !== 'soulstone'"
                    :type="ability.suits"
                    class-name="text-[9px] inline-block"
                /><template v-if="ability.defensive_ability_type"
                    ><template v-if="ability.suits && ability.suits !== 'soulstone'">, </template
                    ><GameIcon :type="ability.defensive_ability_type" class-name="text-[9px] inline-block" /></template
                >)
            </span>
        </div>
        <p v-if="ability.description" class="leading-relaxed">
            <GameText :text="ability.description" icon-class="text-[9px] inline-block align-text-bottom" />
        </p>
    </div>
</template>
