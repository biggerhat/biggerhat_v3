<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import TournamentCard, { type TournamentSummary } from '@/components/Tournament/TournamentCard.vue';
import { CARD_HOVER_PROMINENT } from '@/lib/cardHover';
import { csrfToken } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Search, Trophy } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{
    my_tournaments: TournamentSummary[];
    public_tournaments: TournamentSummary[];
    public_pagination: { current_page: number; last_page: number; total: number };
    filters: { q: string; ruleset: string; when: '' | 'upcoming' | 'past' };
    ruleset_options: { key: string; label: string }[];
}>();

// Find-an-event filters live in the URL (shareable, back-button friendly); typing is debounced.
const search = ref(props.filters.q);
const ruleset = ref(props.filters.ruleset || '__all__');
const when = ref<'' | 'upcoming' | 'past'>(props.filters.when);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
const applyFilters = (page = 1) =>
    router.get(
        route('tournaments.index'),
        {
            q: search.value.trim() || undefined,
            ruleset: ruleset.value === '__all__' ? undefined : ruleset.value,
            when: when.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true, only: ['public_tournaments', 'public_pagination', 'filters'] },
    );
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
};
onBeforeUnmount(() => clearTimeout(searchTimer));
const setWhen = (value: '' | 'upcoming' | 'past') => {
    when.value = value;
    applyFilters();
};
const filtered = computed(() => !!(props.filters.q || props.filters.ruleset || props.filters.when));
const WHEN_OPTIONS: { value: '' | 'upcoming' | 'past'; label: string }[] = [
    { value: '', label: 'All' },
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'past', label: 'Past' },
];

// Group a list into what's happening now, what's coming up, and what's finished,
// so a long history doesn't bury the events people actually came to find.
const PAST_VISIBLE = 6;
const showAllPast = ref<Record<string, boolean>>({});
const grouped = (key: string, list: TournamentSummary[]) => {
    const live = list.filter((t) => t.status === 'active');
    const upcoming = list.filter((t) => t.status === 'draft' || t.status === 'registration');
    const past = list.filter((t) => t.status === 'completed');
    return [
        { id: 'live', title: 'Happening now', items: live },
        { id: 'upcoming', title: 'Upcoming', items: upcoming },
        {
            id: 'past',
            title: 'Past events',
            items: showAllPast.value[key] ? past : past.slice(0, PAST_VISIBLE),
            hidden: showAllPast.value[key] ? 0 : Math.max(0, past.length - PAST_VISIBLE),
        },
    ].filter((g) => g.items.length);
};
const myGroups = computed(() => grouped('mine', props.my_tournaments));
const publicGroups = computed(() => grouped('public', props.public_tournaments));

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);
// Super-admins see a trash icon on every tournament card and can delete in
// any state (creators are still gated to Draft on the controller side).
const isSuperAdmin = computed(() => !!page.props.auth.is_super_admin);

const confirm = useConfirm();
const toast = useToast();

const removeTournament = async (t: TournamentSummary) => {
    if (
        !(await confirm({
            title: `Delete ${t.name}?`,
            message: 'This permanently removes the tournament along with its rounds, pairings, games, and player roster. This cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
        }))
    ) {
        return;
    }
    try {
        const res = await fetch(route('tournaments.destroy', t.uuid), {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        if (!res.ok && res.status !== 302) {
            const body = await res.json().catch(() => ({}));
            toast.error(body?.error ?? 'Could not delete tournament.');
            return;
        }
        toast.success(`${t.name} deleted.`);
        router.reload({ only: ['my_tournaments', 'public_tournaments'], preserveScroll: true });
    } catch {
        toast.error('Network error deleting tournament.');
    }
};
</script>

<template>
    <Head title="Tournaments" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Tournaments" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Find, run and follow Malifaux and The Other Side events
                    <Badge class="border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-2xs font-bold text-amber-600 dark:text-amber-400">Beta</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- Create Tournament CTA -->
            <Link
                v-if="isLoggedIn"
                :href="route('tournaments.create')"
                class="group mb-6 block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <Card :class="CARD_HOVER_PROMINENT">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                        >
                            <Plus class="size-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Create Tournament</p>
                            <p class="text-sm text-muted-foreground">Set up a new Gaining Grounds event</p>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <Link
                v-else
                :href="route('login')"
                class="group mb-6 block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <Card :class="CARD_HOVER_PROMINENT">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Trophy class="size-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Run your own tournament</p>
                            <p class="text-sm text-muted-foreground">Log in or sign up to set up a Gaining Grounds event — it's free for everyone.</p>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <!-- My Tournaments -->
            <section v-if="isLoggedIn && my_tournaments.length" class="mb-10" aria-labelledby="my-tournaments">
                <h2 id="my-tournaments" class="mb-4 text-lg font-semibold">My Tournaments</h2>
                <div v-for="group in myGroups" :key="'my-' + group.id" class="mb-5">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ group.title }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <TournamentCard
                            v-for="t in group.items"
                            :key="t.id"
                            :tournament="t"
                            :href="route('tournaments.manage', t.uuid)"
                            meta="rounds"
                            :deletable="isSuperAdmin"
                            @delete="removeTournament"
                        />
                    </div>
                    <Button v-if="group.hidden" variant="ghost" size="sm" class="mt-2" @click="showAllPast.mine = true">
                        Show {{ group.hidden }} more past events
                    </Button>
                </div>
            </section>

            <!-- Public Tournaments -->
            <section v-if="public_tournaments.length || filtered" aria-labelledby="public-tournaments">
                <h2 id="public-tournaments" class="mb-3 text-lg font-semibold">Public Tournaments</h2>

                <form class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center" role="search" @submit.prevent="applyFilters()">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" aria-hidden="true" />
                        <Input
                            v-model="search"
                            type="search"
                            class="h-9 pl-8 text-sm"
                            placeholder="Search by name or place…"
                            aria-label="Search tournaments"
                            @input="onSearchInput"
                        />
                    </div>
                    <Select v-model="ruleset" @update:model-value="applyFilters()">
                        <SelectTrigger class="h-9 w-full text-sm sm:w-56" aria-label="Game format"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">All formats</SelectItem>
                            <SelectItem v-for="r in ruleset_options" :key="r.key" :value="r.key">{{ r.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="flex gap-1" role="group" aria-label="When">
                        <Button
                            v-for="o in WHEN_OPTIONS"
                            :key="o.value"
                            type="button"
                            size="sm"
                            class="h-9"
                            :variant="when === o.value ? 'default' : 'outline'"
                            :aria-pressed="when === o.value"
                            @click="setWhen(o.value)"
                        >
                            {{ o.label }}
                        </Button>
                    </div>
                </form>

                <p v-if="!public_tournaments.length" class="py-8 text-center text-sm text-muted-foreground" role="status">
                    No tournaments match those filters.
                </p>
                <div v-for="group in publicGroups" :key="'pub-' + group.id" class="mb-5">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ group.title }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <TournamentCard
                            v-for="t in group.items"
                            :key="'pub-' + t.id"
                            :tournament="t"
                            :href="route('tournaments.view', t.uuid)"
                            meta="creator"
                            :deletable="isSuperAdmin"
                            @delete="removeTournament"
                        />
                    </div>
                    <Button v-if="group.hidden" variant="ghost" size="sm" class="mt-2" @click="showAllPast.public = true">
                        Show {{ group.hidden }} more past events
                    </Button>
                </div>

                <nav v-if="public_pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm" aria-label="Tournament pages">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="public_pagination.current_page <= 1"
                        @click="applyFilters(public_pagination.current_page - 1)"
                    >
                        <ChevronLeft class="mr-1 size-4" /> Previous
                    </Button>
                    <span class="text-muted-foreground"
                        >Page {{ public_pagination.current_page }} of {{ public_pagination.last_page }} · {{ public_pagination.total }} events</span
                    >
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="public_pagination.current_page >= public_pagination.last_page"
                        @click="applyFilters(public_pagination.current_page + 1)"
                    >
                        Next <ChevronRight class="ml-1 size-4" />
                    </Button>
                </nav>
            </section>

            <!-- Empty state -->
            <EmptyState
                v-if="!my_tournaments.length && !public_tournaments.length && !filtered"
                :icon="Trophy"
                title="No tournaments yet"
                :description="
                    isLoggedIn ? 'Create your first Gaining Grounds event to get started.' : 'Log in to create the first Gaining Grounds event.'
                "
            >
                <template v-if="isLoggedIn" #action>
                    <Link :href="route('tournaments.create')">
                        <Button><Plus class="mr-2 size-4" /> Create Tournament</Button>
                    </Link>
                </template>
            </EmptyState>
        </div>
    </div>
</template>
