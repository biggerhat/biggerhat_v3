<script setup lang="ts">
import InsightBars from '@/components/Tournament/InsightBars.vue';
import type { InsightBreakdownRow } from '@/types/tournament';
import { computed } from 'vue';

/** Win rate by faction / leader: bars for the rate, the record underneath for anyone who wants the numbers. */
const props = defineProps<{ rows: InsightBreakdownRow[]; noun: string }>();
const items = computed(() =>
    props.rows.map((r) => ({ label: r.label, value: Math.round(r.win_rate ?? 0), note: `${r.wins}-${r.losses}-${r.ties}` })),
);
</script>

<template>
    <div>
        <InsightBars :items="items" :max="100" unit="%" />
        <p class="mt-2 text-2xs text-muted-foreground">
            Win rate counts a tie as half a win. The record beside each bar is wins-losses-ties, {{ noun }}.
        </p>
    </div>
</template>
