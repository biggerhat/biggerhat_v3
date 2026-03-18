<script setup lang="ts">
import FilterPanel from '@/components/FilterPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LayoutGrid, List, Search, X } from 'lucide-vue-next';

defineProps<{
    nameSearch: string | null;
    pageView: string | null;
    activeFilterCount: number;
    placeholder?: string;
    views?: { value: string; icon: 'cards' | 'table'; label: string }[];
    hasFilters?: boolean;
}>();

const emit = defineEmits<{
    'update:nameSearch': [value: string | null];
    'update:pageView': [value: string];
    nameKeydown: [e: KeyboardEvent];
    clearSearch: [];
    filter: [];
    clear: [];
}>();

const handleKeydown = (e: KeyboardEvent) => {
    emit('nameKeydown', e);
};
</script>

<template>
    <!-- Search bar -->
    <div class="container mx-auto mb-3 sm:px-4">
        <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                :model-value="nameSearch"
                type="text"
                :placeholder="placeholder ?? 'Search by name...'"
                class="border-2 border-primary pl-10 pr-10"
                @update:model-value="emit('update:nameSearch', $event as string)"
                @keydown="handleKeydown"
            />
            <button
                v-if="nameSearch"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                @click="emit('clearSearch')"
            >
                <X class="h-4 w-4" />
            </button>
        </div>
    </div>

    <!-- Tabs + filter trigger -->
    <div class="container mx-auto mb-2 flex items-center justify-between sm:px-4">
        <Tabs :model-value="pageView ?? 'cards'" @update:model-value="emit('update:pageView', $event as string)">
            <TabsList>
                <TabsTrigger value="cards">
                    <LayoutGrid class="h-4 w-4" />
                    <span class="hidden sm:inline">Cards</span>
                </TabsTrigger>
                <TabsTrigger value="table">
                    <List class="h-4 w-4" />
                    <span class="hidden sm:inline">Table</span>
                </TabsTrigger>
            </TabsList>
        </Tabs>
        <div class="flex items-center gap-2">
            <Badge v-if="activeFilterCount > 0" variant="secondary" class="text-xs">
                {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
            </Badge>
            <FilterPanel v-if="hasFilters" :filter-count="activeFilterCount" @filter="emit('filter')" @clear="emit('clear')">
                <slot name="filters" />
            </FilterPanel>
        </div>
    </div>
</template>
