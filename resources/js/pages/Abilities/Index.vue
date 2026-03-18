<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link } from '@inertiajs/vue3';
import { ScrollText, Users } from 'lucide-vue-next';
import { computed } from 'vue';

import AbilityCard from '@/components/AbilityCard.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';

const booleanOptions = [
    { name: 'Yes', value: 'true' },
    { name: 'No', value: 'false' },
];

const props = defineProps({
    abilities: {
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
    ability_names: {
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
    defensive_ability_types: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
});

const filterKeys = ['name', 'name_search', 'suits', 'defensive_ability_type', 'costs_stone', 'description'] as const;

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name: null as string | null,
        name_search: null as string | null,
        suits: null as string | null,
        defensive_ability_type: null as string | null,
        costs_stone: null as string | null,
        description: null as string | null,
        page_view: null as string | null,
    },
    {
        routeName: 'abilities.index',
        filterKeys,
        only: ['abilities', 'result_count'],
    },
);

const abilityCount = computed(() => props.abilities?.data?.length ?? 0);
const { delays } = useStaggeredEntry(abilityCount);

const formatDefensiveType = (type: string) => {
    if (!type) return '';
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};
</script>

<template>
    <Head title="Abilities" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Ability Directory" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'ability' : 'abilities' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            @update:page-view="handleViewChange"
            :active-filter-count="activeFilterCount"
            placeholder="Search abilities by name..."
            has-filters
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
                            placeholder="Select Ability"
                            :options="props.ability_names"
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
                        <label class="text-sm font-medium">Defensive Type</label>
                        <ClearableSelect
                            v-model="filterParams.defensive_ability_type"
                            placeholder="Any Type"
                            :options="props.defensive_ability_types"
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
                            <ClearableSelect v-model="filterParams.name" placeholder="Select Ability" :options="props.ability_names" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Suit</label>
                            <ClearableSelect v-model="filterParams.suits" placeholder="Any Suit" :options="props.suits" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Defensive Type</label>
                            <ClearableSelect
                                v-model="filterParams.defensive_ability_type"
                                placeholder="Any Type"
                                :options="props.defensive_ability_types"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Costs Soulstone</label>
                            <ClearableSelect v-model="filterParams.costs_stone" placeholder="Any" :options="booleanOptions" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Description</label>
                            <Input v-model="filterParams.description" type="text" placeholder="Search description..." class="h-8 text-xs" />
                        </div>

                        <!-- Action buttons -->
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Results area -->
                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="6" />
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
                                    <TableHead>Defensive Type</TableHead>
                                    <TableHead>Costs Stone</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.abilities?.data?.length">
                                    <TableRow v-for="ability in props.abilities.data" :key="ability.id">
                                        <TableCell class="font-medium">
                                            <span class="inline-flex items-center gap-1">
                                                <GameIcon v-if="ability.costs_stone" type="soulstone" class-name="h-4 inline-block" />
                                                {{ ability.name }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <GameIcon v-if="ability.suits" :type="ability.suits" class-name="h-4 inline-block" />
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                v-if="ability.defensive_ability_type"
                                                variant="outline"
                                                class="inline-flex items-center gap-1 text-xs"
                                            >
                                                <GameIcon :type="ability.defensive_ability_type" class-name="h-3.5 inline-block" />
                                                {{ formatDefensiveType(ability.defensive_ability_type) }}
                                            </Badge>
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>{{ ability.costs_stone ? 'Yes' : 'No' }}</TableCell>
                                        <TableCell class="max-w-md">
                                            <GameText :text="ability.description" icon-class="h-4 inline-block align-text-bottom" />
                                        </TableCell>
                                        <TableCell>
                                            <span class="inline-flex flex-wrap items-center gap-1">
                                                <Link
                                                    v-if="ability.characters_count === 1 && ability.characters?.length === 1"
                                                    :href="
                                                        route('characters.view', {
                                                            character: ability.characters[0].slug,
                                                            miniature: ability.characters[0].standard_miniatures?.[0]?.id,
                                                            slug: ability.characters[0].standard_miniatures?.[0]?.slug ?? 'view',
                                                        })
                                                    "
                                                    class="inline-flex items-center gap-1 text-primary hover:underline"
                                                >
                                                    <Users class="h-3 w-3 shrink-0" />
                                                    {{ ability.characters[0].display_name }}
                                                </Link>
                                                <Link
                                                    v-else-if="ability.characters_count > 1"
                                                    :href="route('search.view', { ability: ability.name })"
                                                    class="inline-flex items-center gap-1 text-primary hover:underline"
                                                >
                                                    <Users class="h-3 w-3 shrink-0" />
                                                    {{ ability.characters_count }}
                                                </Link>
                                                <template v-else-if="ability.upgrades?.length">
                                                    <ScrollText class="h-3 w-3 shrink-0 text-muted-foreground" />
                                                    <Link
                                                        v-for="upgrade in ability.upgrades"
                                                        :key="upgrade.slug"
                                                        :href="route('upgrades.view', upgrade.slug)"
                                                        class="text-primary hover:underline"
                                                    >
                                                        {{ upgrade.name }}
                                                    </Link>
                                                </template>
                                                <span v-else class="text-muted-foreground">0</span>
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
                        <InertiaPagination :paginator="props.abilities" :only="['abilities', 'result_count']" />
                    </div>
                    <div v-else>
                        <template v-if="props.abilities?.data?.length">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <AbilityCard
                                    v-for="(ability, index) in props.abilities.data"
                                    :key="ability.id"
                                    :ability="ability"
                                    class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                    :style="delays[index]"
                                >
                                    <template #footer>
                                        <template v-if="ability.characters_count === 1 && ability.characters?.length === 1">
                                            <Link
                                                :href="
                                                    route('characters.view', {
                                                        character: ability.characters[0].slug,
                                                        miniature: ability.characters[0].standard_miniatures?.[0]?.id,
                                                        slug: ability.characters[0].standard_miniatures?.[0]?.slug ?? 'view',
                                                    })
                                                "
                                                class="text-primary hover:underline"
                                            >
                                                {{ ability.characters[0].display_name }}
                                            </Link>
                                        </template>
                                        <Link
                                            v-else-if="ability.characters_count > 1"
                                            :href="route('search.view', { ability: ability.name })"
                                            class="text-primary hover:underline"
                                        >
                                            {{ ability.characters_count }} characters
                                        </Link>
                                        <template v-else-if="ability.upgrades?.length">
                                            <Link
                                                v-for="upgrade in ability.upgrades"
                                                :key="upgrade.slug"
                                                :href="route('upgrades.view', upgrade.slug)"
                                                class="text-primary hover:underline"
                                            >
                                                {{ upgrade.name }}
                                            </Link>
                                        </template>
                                        <span v-else class="text-muted-foreground">0 characters</span>
                                    </template>
                                </AbilityCard>
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.abilities" :only="['abilities', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
