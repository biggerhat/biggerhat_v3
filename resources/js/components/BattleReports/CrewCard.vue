<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import { Link } from '@inertiajs/vue3';

/** The crew a battle report's writer played: master and models. The crew's own name and notes are not shown. */
export interface ReportCrew {
    faction: string | null;
    faction_label: string | null;
    master: string | null;
    encounter_size: number;
    models: { name: string; count: number; cost: number | null }[];
    cost: number;
    url: string | null;
}
defineProps<{ crew: ReportCrew }>();
</script>

<template>
    <div class="rounded-lg border bg-muted/20 p-3" data-testid="report-crew">
        <div class="mb-2 flex items-center gap-2">
            <FactionLogo v-if="crew.faction" :faction="crew.faction" class-name="size-5 shrink-0" />
            <p class="text-sm font-medium">
                Crew<template v-if="crew.master">: {{ crew.master }}</template>
                <span class="font-normal text-muted-foreground"
                    ><template v-if="crew.faction_label"> · {{ crew.faction_label }}</template> · {{ crew.encounter_size }}ss</span
                >
            </p>
        </div>
        <ul v-if="crew.models.length" class="grid gap-x-4 gap-y-0.5 text-sm sm:grid-cols-2">
            <li v-for="m in crew.models" :key="m.name" class="flex justify-between gap-2">
                <span class="truncate">{{ m.count > 1 ? `${m.count}× ` : '' }}{{ m.name }}</span>
                <span v-if="m.cost != null" class="shrink-0 tabular-nums text-muted-foreground">{{ m.cost * m.count }}ss</span>
            </li>
        </ul>
        <p class="mt-2 flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground">
            <span v-if="crew.cost">{{ crew.cost }}ss of models</span>
            <Link v-if="crew.url" :href="crew.url" class="underline">Open the crew list</Link>
        </p>
    </div>
</template>
