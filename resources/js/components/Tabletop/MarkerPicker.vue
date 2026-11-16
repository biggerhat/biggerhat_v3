<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { searchMarkers, type MarkerOption } from '@/lib/tabletop/geometry';
import { MapPin, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    markers: MarkerOption[];
    /** How many of each marker (by id) are already on the table for this side. */ used?: Record<number, number>;
    limit?: number;
}>();
const emit = defineEmits<{ add: [marker: MarkerOption] }>();

const query = ref('');
const results = computed(() => searchMarkers(props.markers, query.value, 12));
const total = computed(() => props.markers.length);
</script>

<template>
    <div class="space-y-2 text-sm" data-testid="marker-picker">
        <div class="text-muted-foreground">{{ total }} to choose from</div>
        <label class="relative block">
            <span class="sr-only">Search markers</span>
            <Search class="pointer-events-none absolute left-2 top-2.5 size-4 text-muted-foreground" />
            <input
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Search markers"
                class="w-full rounded-md border bg-background py-1.5 pl-8 pr-2"
                data-testid="marker-search"
            />
        </label>
        <ul v-if="results.length" class="max-h-72 divide-y overflow-y-auto rounded-md border" data-testid="marker-results">
            <li v-for="m in results" :key="m.id" class="flex items-center gap-2 px-2 py-1.5" data-testid="marker-result">
                <MapPin class="size-4 shrink-0 text-muted-foreground" />
                <span class="min-w-0 flex-1 truncate">{{ m.name }}</span>
                <span class="shrink-0 text-muted-foreground" data-testid="marker-base">{{ m.base_mm }} mm</span>
                <span v-if="limit !== undefined" class="shrink-0 text-xs text-muted-foreground" data-testid="marker-used"
                    >{{ used?.[m.id] ?? 0 }}/{{ limit }}</span
                >
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="limit !== undefined && (used?.[m.id] ?? 0) >= limit"
                    :aria-label="`Add ${m.name}`"
                    data-testid="marker-add"
                    @click="emit('add', m)"
                    >Add</Button
                >
            </li>
        </ul>
        <p v-else class="text-muted-foreground">{{ total ? `No marker matches "${query}".` : 'There are no markers in the database yet.' }}</p>
    </div>
</template>
