<script setup lang="ts">
import { describeStages, STAGE_DOT } from '@/lib/collectionStages';
import { computed } from 'vue';

/** One dot per model, coloured by its stage, so a row shows which copies are done without opening it. */
const props = defineProps<{ stages: string[] }>();

const SHOWN = 12;
const dots = computed(() => props.stages.slice(0, SHOWN));
const more = computed(() => Math.max(0, props.stages.length - SHOWN));
const label = computed(() => describeStages(props.stages));
</script>

<template>
    <span
        class="inline-flex items-center gap-1"
        role="img"
        :aria-label="label ? `Models: ${label}` : 'No models'"
        :title="label"
        data-testid="stage-pips"
    >
        <span v-for="(stage, i) in dots" :key="i" class="size-2.5 shrink-0 rounded-full" :class="STAGE_DOT[stage] ?? STAGE_DOT.unbuilt" />
        <span v-if="more" class="text-2xs text-muted-foreground" aria-hidden="true">+{{ more }}</span>
    </span>
</template>
