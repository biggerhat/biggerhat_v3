<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head } from '@inertiajs/vue3';
import { Swords, Users } from 'lucide-vue-next';
import { computed } from 'vue';

import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import TriggerCard from '@/components/TriggerCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';

const booleanOptions = [
    { name: 'Yes', value: 'true' },
    { name: 'No', value: 'false' },
];

const props = defineProps({
    triggers: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    result_count: {
        type: Number,
        required: false,
        default: 0,
    },
    trigger_names: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
});

const filterKeys = ['name', 'name_search', 'suits', 'costs_stone', 'description'] as const;

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name: null as string | null,
        name_search: null as string | null,
        suits: null as string | null,
        costs_stone: null as string | null,
        description: null as string | null,
        page_view: null as string | null,
    },
    {
        routeName: 'triggers.index',
        filterKeys,
        only: ['triggers', 'result_count'],
    },
);

const triggerCount = computed(() => props.triggers?.data?.length ?? 0);
const { delays } = useStaggeredEntry(triggerCount);

const uniqueCharactersForTrigger = (trigger: any) => {
    const map = new Map();
    for (const action of trigger.actions ?? []) {
        for (const char of action.characters ?? []) {
            if (!map.has(char.id)) map.set(char.id, char);
        }
    }
    return map.size;
};
</script>

<template>
    <Head title="Triggers" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Trigger Directory" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'trigger' : 'triggers' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search triggers by name..."
            has-filters
            @update:page-view="handleViewChange"
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        >
            <template #filters>
                <div class="grid gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Name</label>
                        <ClearableSelect
                            v-model="filterParams.name"
                            placeholder="Select Trigger"
                            :options="props.trigger_names"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Suit</label>
                        <ClearableSelect
                            v-model="filterParams.suits"
                            placeholder="Any Suit"
                            :options="props.suits"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Costs Soulstone</label>
                        <ClearableSelect
                            v-model="filterParams.costs_stone"
                            placeholder="Any"
                            :options="booleanOptions"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Description</label>
                        <Input
                            v-model="filterParams.description"
                            type="text"
                            placeholder="Search description..."
                            class="h-8 border-2 border-primary text-xs"
                        />
                    </div>
                </div>
            </template>
        </ListSearchBar>

        <!-- Main content area -->
        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-64 shrink-0 md:block">
                    <div class="space-y-3 pr-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Name</label>
                            <ClearableSelect v-model="filterParams.name" placeholder="Select Trigger" :options="props.trigger_names" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Suit</label>
                            <ClearableSelect v-model="filterParams.suits" placeholder="Any Suit" :options="props.suits" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Costs Soulstone</label>
                            <ClearableSelect v-model="filterParams.costs_stone" placeholder="Any" :options="booleanOptions" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Description</label>
                            <Input v-model="filterParams.description" type="text" placeholder="Search description..." class="h-8 text-xs" />
                        </div>
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Results area -->
                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="5" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
                        </div>
                    </div>
                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Suit</TableHead>
                                    <TableHead>Costs Stone</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Actions</TableHead>
                                    <TableHead>Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.triggers?.data?.length">
                                    <TableRow v-for="trigger in props.triggers.data" :key="trigger.id">
                                        <TableCell class="font-medium">
                                            <span class="inline-flex items-center gap-1">
                                                <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="h-4 inline-block" />
                                                <GameIcon v-for="n in trigger.stone_cost ?? 0" :key="n" type="soulstone" class-name="h-4 inline-block" />
                                                {{ trigger.name }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="h-4 inline-block" />
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>{{ trigger.stone_cost > 0 ? 'Yes' : 'No' }}</TableCell>
                                        <TableCell class="max-w-md">
                                            <GameText
                                                v-if="trigger.description"
                                                :text="trigger.description"
                                                icon-class="h-4 inline-block align-text-bottom"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <span class="inline-flex items-center gap-1">
                                                <Swords class="h-3 w-3 shrink-0 text-muted-foreground" />
                                                {{ trigger.actions_count ?? trigger.actions?.length ?? 0 }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span class="inline-flex items-center gap-1">
                                                <Users class="h-3 w-3 shrink-0 text-muted-foreground" />
                                                {{ uniqueCharactersForTrigger(trigger) }}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow>
                                        <TableCell :colspan="6">
                                            <EmptyState />
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                        <InertiaPagination :paginator="props.triggers" :only="['triggers', 'result_count']" />
                    </div>
                    <div v-else>
                        <template v-if="props.triggers?.data?.length">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <TriggerCard
                                    v-for="(trigger, index) in props.triggers.data"
                                    :key="trigger.id"
                                    :trigger="trigger"
                                    class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                    :style="delays[index]"
                                />
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.triggers" :only="['triggers', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
