<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import TournamentCard, { type TournamentSummary } from '@/components/Tournament/TournamentCard.vue';
import { CARD_HOVER_PROMINENT } from '@/lib/cardHover';
import { csrfToken } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Info, Plus, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    my_tournaments: TournamentSummary[];
    public_tournaments: TournamentSummary[];
}>();

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
const canCreateTournaments = computed(() => (page.props.auth.permissions ?? []).includes('create_tournaments'));
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
                    Organize and track Gaining Grounds events
                    <Badge class="border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-2xs font-bold text-amber-600 dark:text-amber-400">Beta</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- TO opt-in notice — shown to users without create-tournament
                 permission. Tracker access is gated while in beta; this points
                 prospective TOs to the right channel for getting onboarded. -->
            <div
                v-if="!canCreateTournaments"
                class="mb-6 flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 text-sm dark:bg-amber-500/10"
            >
                <Info class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                <div class="space-y-1">
                    <p class="font-medium text-amber-900 dark:text-amber-200">Are you a Tournament Organizer?</p>
                    <p class="text-muted-foreground">
                        The Tournament Tracker is in beta and access is request-only while we polish it. If you'd like to run an event with it, reach
                        out to an admin or
                        <Link :href="route('feedback.show')" class="font-medium text-primary underline-offset-2 hover:underline">
                            send us a note via the feedback page
                        </Link>
                        and we'll get you set up.
                    </p>
                </div>
            </div>

            <!-- Create Tournament CTA -->
            <Link
                v-if="canCreateTournaments"
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
            <section v-if="public_tournaments.length" aria-labelledby="public-tournaments">
                <h2 id="public-tournaments" class="mb-4 text-lg font-semibold">Public Tournaments</h2>
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
            </section>

            <!-- Empty state -->
            <EmptyState
                v-if="!my_tournaments.length && !public_tournaments.length"
                :icon="Trophy"
                title="No tournaments yet"
                :description="
                    canCreateTournaments
                        ? 'Create your first Gaining Grounds event to get started.'
                        : 'Tournament creation is restricted. Ask an admin for the Tournament Organizer role.'
                "
            >
                <template v-if="canCreateTournaments" #action>
                    <Link :href="route('tournaments.create')">
                        <Button><Plus class="mr-2 size-4" /> Create Tournament</Button>
                    </Link>
                </template>
            </EmptyState>
        </div>
    </div>
</template>
