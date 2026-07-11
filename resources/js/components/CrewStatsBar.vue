<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';

withDefaults(
    defineProps<{
        totalSpent: number;
        encounterSize: number;
        soulstonePool: number;
        ookCount: number;
        rowClass?: string;
    }>(),
    { rowClass: 'mb-2' },
);
</script>

<template>
    <div :class="['flex flex-wrap items-center gap-x-4 gap-y-2 text-sm', rowClass]">
        <div class="flex items-center gap-1">
            <span class="text-muted-foreground">Spent:</span>
            <span class="font-medium" :class="totalSpent > encounterSize ? 'text-destructive' : ''"> {{ totalSpent }} / {{ encounterSize }} </span>
            <GameIcon type="soulstone" class-name="h-4 inline-block" />
        </div>
        <div class="flex items-center gap-1">
            <span class="text-muted-foreground">Pool:</span>
            <span class="font-medium">{{ soulstonePool }}</span>
            <GameIcon type="soulstone" class-name="h-4 inline-block" />
        </div>
        <div class="flex items-center gap-1">
            <span class="text-muted-foreground">OOK:</span>
            <span class="font-medium" :class="ookCount >= 2 ? 'text-amber-600 dark:text-amber-400' : ''"> {{ ookCount }} / 2 </span>
        </div>
    </div>
</template>
