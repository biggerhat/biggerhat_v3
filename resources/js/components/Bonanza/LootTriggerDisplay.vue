<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';

interface TriggerLike {
    name: string;
    suits?: string | null;
    stone_cost?: number;
    description?: string | null;
}

defineProps<{
    trigger: TriggerLike;
}>();
</script>

<template>
    <div class="text-[8px] leading-relaxed text-muted-foreground">
        <span class="inline-flex items-center gap-0.5 font-semibold text-foreground">
            <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="text-[9px] inline-block" />
            <GameIcon v-for="n in trigger.stone_cost ?? 0" :key="`s-${n}`" type="soulstone" class-name="text-[9px] inline-block" />
            {{ trigger.name }}:
        </span>
        {{ ' ' }}
        <GameText v-if="trigger.description" :text="trigger.description" icon-class="text-[9px] inline-block align-text-bottom" />
    </div>
</template>
