<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head, Link } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface ResultRow {
    type: string;
    type_label: string;
    slug: string;
    name: string;
    snippet: string | null;
    url: string | null;
}

interface TypeOption {
    value: string;
    name: string;
}

const props = defineProps<{
    name_search: string;
    selected_types: string[];
    all_types: TypeOption[];
    results: ResultRow[];
}>();

// Mirror the standard TOS index pattern — useListFiltering for URL sync,
// `name_search` as the query parameter. Type filter rides on filterKeys
// and is serialized as a comma-separated string (`types=units,abilities`)
// to keep URLs flat.
const { filterParams, filter, handleNameKeydown, clearNameSearch } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        types: props.selected_types.length === props.all_types.length ? null : (props.selected_types.join(',') as string | null),
    },
    {
        routeName: 'tos.search',
        filterKeys: ['types'],
        only: ['name_search', 'selected_types', 'results'],
    },
);

const activeTypes = computed<string[]>(() => {
    const raw = filterParams.value.types;
    if (!raw) return props.all_types.map((t) => t.value);
    return raw.split(',').filter(Boolean);
});

watch(
    () => props.selected_types,
    (next) => {
        filterParams.value.types = next.length === props.all_types.length ? null : next.join(',');
    },
);

function toggleType(value: string) {
    const set = new Set(activeTypes.value);
    if (set.has(value)) {
        set.delete(value);
    } else {
        set.add(value);
    }
    const arr = Array.from(set);
    filterParams.value.types = arr.length === props.all_types.length || arr.length === 0 ? null : arr.join(',');
    filter();
}

const grouped = computed(() => {
    const groups: Record<string, { label: string; rows: ResultRow[] }> = {};
    for (const row of props.results) {
        if (!groups[row.type]) {
            groups[row.type] = { label: row.type_label, rows: [] };
        }
        groups[row.type].rows.push(row);
    }
    return groups;
});

const hasQuery = computed(() => (filterParams.value.name_search ?? '').length >= 2);
</script>

<template>
    <Head title="Search — TOS" />
    <div class="relative">
        <PageBanner title="Search" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Search across all TOS database entries
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mb-3 sm:px-4">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    :model-value="filterParams.name_search"
                    type="text"
                    placeholder="Search units, allegiances, abilities, actions, assets…"
                    class="border-2 border-primary pl-10 pr-10"
                    @update:model-value="filterParams.name_search = ($event as string)"
                    @keydown="handleNameKeydown"
                />
                <button
                    v-if="filterParams.name_search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearNameSearch"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="container mx-auto mb-3 sm:px-4">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="t in all_types"
                    :key="t.value"
                    type="button"
                    :class="[
                        'rounded-md px-2 py-0.5 text-[11px] transition-colors',
                        activeTypes.includes(t.value) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent',
                    ]"
                    @click="toggleType(t.value)"
                >
                    {{ t.name }}
                </button>
            </div>
        </div>

        <div class="container mx-auto sm:px-4">
            <EmptyState
                v-if="!hasQuery"
                :icon="Search"
                title="Type to search"
                description="Enter at least two characters to find matching TOS entries."
            />
            <EmptyState
                v-else-if="!results.length"
                :icon="Search"
                title="No matches"
                :description="`Nothing matched “${filterParams.name_search}” in the selected entry types.`"
            />
            <div v-else class="space-y-4">
                <div v-for="(group, key) in grouped" :key="key">
                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ group.label }} <span class="text-muted-foreground/60">({{ group.rows.length }})</span>
                    </p>
                    <div class="space-y-2">
                        <component
                            :is="row.url ? Link : 'div'"
                            v-for="row in group.rows"
                            :key="`${row.type}-${row.slug}`"
                            :href="row.url ?? undefined"
                            :class="[
                                'block rounded-md border p-3 transition-colors',
                                row.url ? 'hover:border-primary/30 hover:bg-muted/50' : 'cursor-default',
                            ]"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium">{{ row.name }}</span>
                                <Badge variant="outline" class="shrink-0 text-[9px]">{{ row.type_label }}</Badge>
                            </div>
                            <p v-if="row.snippet" class="mt-1 text-[11px] text-muted-foreground">{{ row.snippet }}</p>
                        </component>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
