<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Copy, ExternalLink, Globe, Loader2, Lock, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
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
    is_public?: boolean;
    created_at?: string;
    updated_at?: string;
}

interface CrewMemberDetail {
    display_name: string;
    cost: number;
    effective_cost: number;
    category: string;
    faction: string;
}

interface CrewDetails {
    members: CrewMemberDetail[];
    total_spent: number;
    soulstone_pool: number;
    ook_count: number;
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
    my_crews: CrewCard[];
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

const myCrewCount = computed(() => props.my_crews.length);
const { delays: myCrewDelays } = useStaggeredEntry(myCrewCount);

// ─── My Crew expand/collapse ───
const localMyCrews = ref<(CrewCard & { _deleting?: boolean })[]>([...props.my_crews]);
watch(
    () => props.my_crews,
    (val) => {
        localMyCrews.value = [...val];
    },
);

const expandedCrewId = ref<number | null>(null);
const crewDetailsCache = ref<Record<number, CrewDetails>>({});
const loadingCrewId = ref<number | null>(null);

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const toggleExpand = async (crew: CrewCard) => {
    if (expandedCrewId.value === crew.id) {
        expandedCrewId.value = null;
        return;
    }
    expandedCrewId.value = crew.id;

    if (crewDetailsCache.value[crew.id]) return;

    loadingCrewId.value = crew.id;
    try {
        const response = await fetch(route('tools.crew_builder.details', crew.id), {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        if (response.ok) {
            crewDetailsCache.value[crew.id] = await response.json();
        }
    } finally {
        loadingCrewId.value = null;
    }
};

const categoryLabel = (cat: string): string =>
    ({
        leader: 'Leader',
        totem: 'Totem',
        'in-keyword': 'In Keyword',
        versatile: 'Versatile',
        ook: 'Out of Keyword',
        'fixed-crew': 'Preset',
        required: 'Required',
    })[cat] ?? cat;

const categoryColor = (cat: string): string =>
    ({
        leader: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        totem: 'bg-purple-500/10 text-purple-700 dark:text-purple-400',
        'in-keyword': 'bg-green-500/10 text-green-700 dark:text-green-400',
        versatile: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        ook: 'bg-red-500/10 text-red-700 dark:text-red-400',
        'fixed-crew': 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400',
        required: 'bg-orange-500/10 text-orange-700 dark:text-orange-400',
    })[cat] ?? '';

// ─── Actions ───
const togglingId = ref<number | null>(null);
const togglePublic = async (crew: CrewCard) => {
    togglingId.value = crew.id;
    try {
        const response = await fetch(route('tools.crew_builder.update', crew.id), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({ is_public: !crew.is_public }),
        });
        if (response.ok) {
            const local = localMyCrews.value.find((c) => c.id === crew.id);
            if (local) local.is_public = !crew.is_public;
        }
    } finally {
        togglingId.value = null;
    }
};

const confirmingDeleteId = ref<number | null>(null);
const deleteCrew = async (crew: CrewCard) => {
    if (confirmingDeleteId.value !== crew.id) {
        confirmingDeleteId.value = crew.id;
        return;
    }
    const local = localMyCrews.value.find((c) => c.id === crew.id);
    if (local) (local as any)._deleting = true;

    try {
        const response = await fetch(route('tools.crew_builder.destroy', crew.id), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        if (response.ok) {
            localMyCrews.value = localMyCrews.value.filter((c) => c.id !== crew.id);
            if (expandedCrewId.value === crew.id) expandedCrewId.value = null;
            delete crewDetailsCache.value[crew.id];
        } else {
            if (local) (local as any)._deleting = false;
        }
    } catch {
        if (local) (local as any)._deleting = false;
    }
    confirmingDeleteId.value = null;
};

const copiedId = ref<number | null>(null);
const copyShareLink = (crew: CrewCard) => {
    navigator.clipboard.writeText(route('tools.crew_builder.share', crew.share_code));
    copiedId.value = crew.id;
    setTimeout(() => {
        if (copiedId.value === crew.id) copiedId.value = null;
    }, 2000);
};
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
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto px-4">
            <!-- My Crews -->
            <div v-if="isAuthenticated && localMyCrews.length > 0" class="mb-8">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">My Crews</h2>
                    <Link :href="route('tools.crew_builder.editor')">
                        <Button variant="outline" size="sm" class="gap-1.5">
                            <Pencil class="size-3.5" />
                            Manage in Editor
                        </Button>
                    </Link>
                </div>
                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(crew, index) in localMyCrews" :key="crew.id" class="animate-fade-in-up opacity-0" :style="myCrewDelays[index]">
                        <Card
                            class="transition-all duration-200"
                            :class="[
                                expandedCrewId === crew.id ? 'shadow-md ring-1 ring-primary/50' : 'hover:-translate-y-0.5 hover:shadow-md',
                                (crew as any)._deleting ? 'pointer-events-none opacity-50' : '',
                            ]"
                        >
                            <!-- Card header — clickable to expand -->
                            <CardContent class="flex cursor-pointer items-start gap-3 p-3" @click="toggleExpand(crew)">
                                <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ crew.name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <Badge variant="outline" class="text-[10px]">{{ crew.faction_label }}</Badge>
                                        <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                        <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                                        <Globe v-if="crew.is_public" class="size-3" />
                                        <Lock v-else class="size-3" />
                                        <span>{{ crew.is_public ? 'Public' : 'Private' }}</span>
                                        <span>&middot;</span>
                                        <span>{{ crew.updated_at }}</span>
                                    </div>
                                </div>
                                <ChevronDown
                                    class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                    :class="expandedCrewId === crew.id ? 'rotate-180' : ''"
                                />
                            </CardContent>

                            <!-- Expanded details -->
                            <div v-if="expandedCrewId === crew.id" class="border-t px-3 pb-3 pt-2">
                                <!-- Loading state -->
                                <div v-if="loadingCrewId === crew.id" class="flex items-center justify-center py-4">
                                    <Loader2 class="size-5 animate-spin text-muted-foreground" />
                                </div>

                                <!-- Crew details -->
                                <template v-else-if="crewDetailsCache[crew.id]">
                                    <!-- Stats row -->
                                    <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span>
                                            Spent:
                                            <span
                                                class="font-medium text-foreground"
                                                :class="crewDetailsCache[crew.id].total_spent > crew.encounter_size ? 'text-destructive' : ''"
                                            >
                                                {{ crewDetailsCache[crew.id].total_spent }}/{{ crew.encounter_size }}
                                            </span>
                                            <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                        </span>
                                        <span>
                                            Pool:
                                            <span class="font-medium text-foreground">{{ crewDetailsCache[crew.id].soulstone_pool }}</span>
                                            <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                        </span>
                                        <span>
                                            OOK:
                                            <span
                                                class="font-medium text-foreground"
                                                :class="crewDetailsCache[crew.id].ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''"
                                            >
                                                {{ crewDetailsCache[crew.id].ook_count }}/2
                                            </span>
                                        </span>
                                    </div>

                                    <!-- Member list -->
                                    <div class="space-y-0.5">
                                        <div
                                            v-for="(member, mIdx) in crewDetailsCache[crew.id].members"
                                            :key="mIdx"
                                            :class="factionBackground(member.faction)"
                                            class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                                        >
                                            <div class="flex min-w-0 items-center gap-1.5">
                                                <span class="truncate font-medium">{{ member.display_name }}</span>
                                                <Badge :class="categoryColor(member.category)" class="shrink-0 px-1 py-0 text-[9px]">
                                                    {{ categoryLabel(member.category) }}
                                                </Badge>
                                            </div>
                                            <div v-if="member.effective_cost > 0" class="flex shrink-0 items-center font-bold">
                                                <template v-if="member.category === 'ook'">
                                                    {{ member.effective_cost }}
                                                    <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                    <span class="ml-0.5 text-[9px] font-normal text-red-300">({{ member.cost }}+1)</span>
                                                </template>
                                                <template v-else>
                                                    {{ member.effective_cost }}
                                                    <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action buttons -->
                                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                        <Button
                                            size="sm"
                                            class="h-7 gap-1 text-xs"
                                            as="a"
                                            :href="route('tools.crew_builder.editor') + '?build=' + crew.share_code"
                                        >
                                            <Pencil class="size-3" />
                                            Edit
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 gap-1 text-xs"
                                            as="a"
                                            :href="route('tools.crew_builder.share', crew.share_code)"
                                        >
                                            <ExternalLink class="size-3" />
                                            View
                                        </Button>
                                        <Button variant="outline" size="sm" class="h-7 gap-1 text-xs" @click.stop="copyShareLink(crew)">
                                            <Check v-if="copiedId === crew.id" class="size-3" />
                                            <Copy v-else class="size-3" />
                                            {{ copiedId === crew.id ? 'Copied' : 'Share' }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 gap-1 text-xs"
                                            :disabled="togglingId === crew.id"
                                            @click.stop="togglePublic(crew)"
                                        >
                                            <Globe v-if="crew.is_public" class="size-3" />
                                            <Lock v-else class="size-3" />
                                            {{ crew.is_public ? 'Make Private' : 'Make Public' }}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 gap-1 text-xs text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                            @click.stop="deleteCrew(crew)"
                                        >
                                            <Trash2 class="size-3" />
                                            {{ confirmingDeleteId === crew.id ? 'Confirm?' : 'Delete' }}
                                        </Button>
                                    </div>
                                </template>
                            </div>
                        </Card>
                    </div>
                </div>
                <Separator label="Public Crews" class="mt-8" />
            </div>

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
                        <Card class="h-full transition-all duration-200 group-hover:-translate-y-0.5 group-hover:shadow-md">
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
