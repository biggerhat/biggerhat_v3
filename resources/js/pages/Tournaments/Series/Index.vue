<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { CARD_HOVER_PROMINENT } from '@/lib/cardHover';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronLeft, ChevronRight, Medal, Plus, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

/** Leagues: series of events whose finishing places add up to one leaderboard. */
interface SeriesSummary {
    uuid: string;
    name: string;
    description: string | null;
    creator: string;
    events: number;
    finished: number;
}
const props = defineProps<{
    series: { data: SeriesSummary[]; current_page: number; last_page: number; total: number };
    filters: { q: string };
}>();

const isLoggedIn = computed(() => !!usePage<SharedData>().props.auth?.user);
const search = ref(props.filters.q);
let timer: ReturnType<typeof setTimeout> | null = null;
const apply = (page = 1) => {
    if (timer) clearTimeout(timer);
    router.get(
        route('tournaments.series.index'),
        { q: search.value || undefined, page: page > 1 ? page : undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};
const onSearch = () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => apply(), 300);
};
onBeforeUnmount(() => timer && clearTimeout(timer));
</script>

<template>
    <Head title="Leagues" />
    <PageBanner title="Leagues" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Series of events with one leaderboard
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 pb-8 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Button as-child variant="ghost" class="gap-1.5 text-sm">
                <Link :href="route('tournaments.index')"><ArrowLeft class="size-4" /> Tournaments</Link>
            </Button>
            <Button v-if="isLoggedIn" as-child size="sm" class="gap-1.5">
                <Link :href="route('tournaments.series.create')" data-testid="new-series"><Plus class="size-4" /> New league</Link>
            </Button>
        </div>

        <div class="relative max-w-sm">
            <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" aria-hidden="true" />
            <Input v-model="search" class="pl-8" placeholder="Search leagues…" aria-label="Search leagues" @input="onSearch" />
        </div>

        <EmptyState
            v-if="!series.data.length"
            :icon="Medal"
            title="No leagues yet"
            description="Create one, then add your events to it from their Settings tab."
        />
        <div v-else class="grid gap-3 sm:grid-cols-2">
            <Link
                v-for="s in series.data"
                :key="s.uuid"
                :href="route('tournaments.series.show', s.uuid)"
                class="group block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <Card :class="CARD_HOVER_PROMINENT">
                    <CardContent class="space-y-1 p-4">
                        <div class="flex items-center gap-2 font-semibold">
                            <Medal class="size-4 text-amber-500" aria-hidden="true" /> {{ s.name }}
                        </div>
                        <p v-if="s.description" class="line-clamp-2 text-sm text-muted-foreground">{{ s.description }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ s.finished }} of {{ s.events }} {{ s.events === 1 ? 'event' : 'events' }} finished · by {{ s.creator }}
                        </p>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <nav v-if="series.last_page > 1" class="flex items-center justify-between text-sm" aria-label="League pages">
            <Button variant="outline" size="sm" :disabled="series.current_page <= 1" @click="apply(series.current_page - 1)"
                ><ChevronLeft class="size-4" /> Previous</Button
            >
            <span class="text-muted-foreground">Page {{ series.current_page }} of {{ series.last_page }}</span>
            <Button variant="outline" size="sm" :disabled="series.current_page >= series.last_page" @click="apply(series.current_page + 1)"
                >Next <ChevronRight class="size-4"
            /></Button>
        </nav>
    </div>
</template>
