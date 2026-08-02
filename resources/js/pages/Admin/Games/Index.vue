<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface GamePlayerRow {
    id: number;
    name: string;
}

interface GameRow {
    id: number;
    uuid: string;
    name: string | null;
    status: string;
    status_label: string;
    format: string;
    format_label: string;
    current_turn: number;
    max_turns: number;
    creator: GamePlayerRow | null;
    players: GamePlayerRow[];
    winner: GamePlayerRow | null;
    is_solo: boolean;
    is_tie: boolean;
    is_observable: boolean;
    created_at: string;
    created_at_iso: string;
    updated_at: string;
    updated_at_iso: string;
}

interface Paginator {
    data: GameRow[];
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
    games: Paginator;
    filters: {
        sort: string;
        direction: 'asc' | 'desc';
        status: string | null;
        format: string | null;
        is_solo: string | null;
        search: string | null;
    };
    statuses: { name: string; value: string }[];
    formats: { name: string; value: string }[];
}>();

const searchText = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? 'all');
const formatFilter = ref(props.filters.format ?? 'all');
const soloFilter = ref(props.filters.is_solo ?? 'all');

const applyFilters = () => {
    const params: Record<string, string> = { sort: props.filters.sort, direction: props.filters.direction };
    if (searchText.value) params.search = searchText.value;
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (formatFilter.value !== 'all') params.format = formatFilter.value;
    if (soloFilter.value !== 'all') params.is_solo = soloFilter.value;

    router.get(route('admin.games.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(searchText, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

const clearSearch = () => {
    searchText.value = '';
    applyFilters();
};

const SORTABLE: Record<string, boolean> = {
    name: true,
    status: true,
    format: true,
    current_turn: true,
    created_at: true,
    updated_at: true,
};

const toggleSort = (column: string) => {
    if (!SORTABLE[column]) return;

    const direction = props.filters.sort === column && props.filters.direction === 'asc' ? 'desc' : 'asc';

    const params: Record<string, string> = { sort: column, direction };
    if (searchText.value) params.search = searchText.value;
    if (statusFilter.value !== 'all') params.status = statusFilter.value;
    if (formatFilter.value !== 'all') params.format = formatFilter.value;
    if (soloFilter.value !== 'all') params.is_solo = soloFilter.value;

    router.get(route('admin.games.index'), params, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <Head title="Games — Admin" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Games">
            <template #subtitle>
                <div class="p-2 text-sm text-muted-foreground">{{ games.total }} {{ games.total === 1 ? 'game' : 'games' }}</div>
            </template>
        </PageBanner>

        <div class="container mx-auto px-4 sm:px-6">
            <!-- Filter bar -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchText" placeholder="Search name, creator, or player..." class="pl-9 pr-9" />
                    <button
                        v-if="searchText"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="clearSearch"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <Select v-model="statusFilter" @update:model-value="applyFilters">
                    <SelectTrigger class="w-full sm:w-44"><SelectValue placeholder="All Statuses" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem v-for="s in statuses" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="formatFilter" @update:model-value="applyFilters">
                    <SelectTrigger class="w-full sm:w-44"><SelectValue placeholder="All Formats" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Formats</SelectItem>
                        <SelectItem v-for="f in formats" :key="f.value" :value="f.value">{{ f.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="soloFilter" @update:model-value="applyFilters">
                    <SelectTrigger class="w-full sm:w-36"><SelectValue placeholder="Solo / Multi" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Solo & Multi</SelectItem>
                        <SelectItem value="1">Solo only</SelectItem>
                        <SelectItem value="0">Multiplayer only</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <EmptyState v-if="!games.data.length" title="No games found" description="No games match the current filters." />

            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="cursor-pointer select-none px-3 py-2" @click="toggleSort('name')">
                                <span class="inline-flex items-center gap-1">
                                    Name
                                    <ArrowUp v-if="filters.sort === 'name' && filters.direction === 'asc'" class="size-3" />
                                    <ArrowDown v-else-if="filters.sort === 'name'" class="size-3" />
                                    <ArrowUpDown v-else class="size-3 opacity-40" />
                                </span>
                            </th>
                            <th class="px-3 py-2">Creator</th>
                            <th class="px-3 py-2">Players</th>
                            <th class="cursor-pointer select-none px-3 py-2" @click="toggleSort('status')">
                                <span class="inline-flex items-center gap-1">
                                    Status
                                    <ArrowUp v-if="filters.sort === 'status' && filters.direction === 'asc'" class="size-3" />
                                    <ArrowDown v-else-if="filters.sort === 'status'" class="size-3" />
                                    <ArrowUpDown v-else class="size-3 opacity-40" />
                                </span>
                            </th>
                            <th class="cursor-pointer select-none px-3 py-2" @click="toggleSort('format')">
                                <span class="inline-flex items-center gap-1">
                                    Format
                                    <ArrowUp v-if="filters.sort === 'format' && filters.direction === 'asc'" class="size-3" />
                                    <ArrowDown v-else-if="filters.sort === 'format'" class="size-3" />
                                    <ArrowUpDown v-else class="size-3 opacity-40" />
                                </span>
                            </th>
                            <th class="cursor-pointer select-none px-3 py-2" @click="toggleSort('current_turn')">
                                <span class="inline-flex items-center gap-1">
                                    Turn
                                    <ArrowUp v-if="filters.sort === 'current_turn' && filters.direction === 'asc'" class="size-3" />
                                    <ArrowDown v-else-if="filters.sort === 'current_turn'" class="size-3" />
                                    <ArrowUpDown v-else class="size-3 opacity-40" />
                                </span>
                            </th>
                            <th class="px-3 py-2">Winner</th>
                            <th class="cursor-pointer select-none px-3 py-2" @click="toggleSort('updated_at')">
                                <span class="inline-flex items-center gap-1">
                                    Updated
                                    <ArrowUp v-if="filters.sort === 'updated_at' && filters.direction === 'asc'" class="size-3" />
                                    <ArrowDown v-else-if="filters.sort === 'updated_at'" class="size-3" />
                                    <ArrowUpDown v-else class="size-3 opacity-40" />
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="game in games.data" :key="game.id" class="border-b last:border-0 hover:bg-accent/30">
                            <td class="px-3 py-2">
                                <Link :href="route('games.observe', game.uuid)" class="font-medium hover:underline" target="_blank">
                                    {{ game.name || game.uuid.slice(0, 8) }}
                                </Link>
                            </td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ game.creator?.name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">
                                <Badge v-if="game.is_solo" variant="outline" class="text-[10px]">Solo</Badge>
                                <span v-else>{{ game.players.map((p) => p.name).join(', ') || '—' }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <Badge variant="outline" class="text-[10px]">{{ game.status_label }}</Badge>
                            </td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ game.format_label }}</td>
                            <td class="px-3 py-2 text-xs tabular-nums text-muted-foreground">{{ game.current_turn }}/{{ game.max_turns }}</td>
                            <td class="px-3 py-2 text-xs text-muted-foreground">
                                <Badge v-if="game.is_tie" variant="outline" class="text-[10px]">Tie</Badge>
                                <span v-else>{{ game.winner?.name ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-muted-foreground" :title="game.updated_at_iso">{{ game.updated_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InertiaPagination :paginator="games" :only="['games']" />
        </div>
    </div>
</template>
