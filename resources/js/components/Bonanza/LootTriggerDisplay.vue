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
    <div class="rounded-md border border-border/60 bg-muted/40 px-2.5 py-1.5 text-xs">
        <div class="flex flex-wrap items-center gap-1 leading-tight">
            <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="h-3.5 inline-block shrink-0" />
            <GameIcon v-for="n in trigger.stone_cost ?? 0" :key="`s-${n}`" type="soulstone" class-name="h-3.5 inline-block shrink-0" />
            <span class="font-semibold">{{ trigger.name }}</span>
        </div>
        <p v-if="trigger.description" class="mt-1 leading-relaxed text-muted-foreground">
            <GameText :text="trigger.description" icon-class="h-3.5 inline-block align-text-bottom" />
        </p>
    </div>
</template>
