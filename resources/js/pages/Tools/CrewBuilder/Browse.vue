<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { CARD_HOVER_GROUP } from '@/lib/cardHover';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Plus, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface CrewCard {
    id: number;
    name: string;
    faction: string;
    faction_label: string;
    faction_color: string;
    faction_logo: string;
    master_name: string | null;
    encounter_size: number;
    share_code: string;
    user_name?: string;
    created_at?: string;
}

interface Paginator {
    data: CrewCard[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    crews: Paginator;
    factions: Record<string, { slug: string; name: string; color: string; logo: string }>;
    active_faction: string | null;
    active_search: string | null;
}>();

const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);

const searchText = ref(props.active_search ?? '');
const selectedFaction = computed(() => props.active_faction ?? 'all');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(searchText, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters(selectedFaction.value, val);
    }, 300);
});

const filterByFaction = (factionSlug: string) => {
    applyFilters(factionSlug, searchText.value);
};

const clearFilters = () => {
    searchText.value = '';
    applyFilters('all', '');
};

const hasActiveFilters = computed(() => !!props.active_faction || !!props.active_search);

const applyFilters = (faction: string, search: string) => {
    const params: Record<string, string> = {};
    if (faction !== 'all') params.faction = faction;
    if (search) params.search = search;
    router.get(route('tools.crew_builder.index'), params, {
        only: ['crews', 'active_faction', 'active_search'],
        preserveState: true,
        replace: true,
    });
};

const crewCount = computed(() => props.crews.data.length);
const { delays } = useStaggeredEntry(crewCount);
</script>

<template>
    <Head title="Browse Crews" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Community Crews">
            <template #subtitle>
                <div class="flex items-center gap-3 p-2 text-sm text-muted-foreground">
                    <span>{{ crews.total }} public {{ crews.total === 1 ? 'crew' : 'crews' }}</span>
                    <Link :href="`${route('tools.crew_builder.editor')}?new`">
                        <Button size="sm" class="gap-1.5">
                            <Plus class="size-3.5" />
                            Build a Crew
                        </Button>
                    </Link>
                    <Link v-if="isAuthenticated" :href="route('tools.crew_builder.editor')">
                        <Button size="sm" variant="outline" class="gap-1.5">
                            <LayoutGrid class="size-3.5" />
                            My Crews
                        </Button>
                    </Link>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- Filter bar -->
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <div class="relative min-w-0 flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchText" placeholder="Search crews..." class="pl-9 pr-9" />
                    <button
                        v-if="searchText"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="searchText = ''"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <Select :model-value="selectedFaction" @update:model-value="filterByFaction($event as string)">
                    <SelectTrigger class="w-full sm:w-48">
                        <SelectValue placeholder="All Factions" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Factions</SelectItem>
                        <SelectItem v-for="(faction, key) in factions" :key="key" :value="String(key)">
                            <span class="flex items-center gap-2">
                                <img :src="faction.logo" class="h-4 w-4" :alt="faction.name" />
                                {{ faction.name }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">Clear</Button>
            </div>

            <!-- Crew grid -->
            <template v-if="crews.data.length">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="(crew, index) in crews.data"
                        :key="crew.id"
                        :href="route('tools.crew_builder.share', crew.share_code)"
                        class="animate-fade-in-up group opacity-0"
                        :style="delays[index]"
                    >
                        <Card :class="['h-full', CARD_HOVER_GROUP]">
                            <CardContent class="flex items-start gap-3 p-3">
                                <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium group-hover:text-primary">{{ crew.name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <Badge variant="outline" class="text-[10px]">{{ crew.faction_label }}</Badge>
                                        <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                        <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                                        <span>{{ crew.user_name }}</span>
                                        <span>&middot;</span>
                                        <span>{{ crew.created_at }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </template>

            <EmptyState
                v-else
                title="No crews found"
                :description="hasActiveFilters ? 'Try adjusting your filters.' : 'No public crews have been shared yet.'"
            />

            <InertiaPagination :paginator="crews" :only="['crews']" />
        </div>
    </div>
</template>
