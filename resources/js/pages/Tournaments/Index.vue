<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useTournamentStatus } from '@/composables/useTournamentStatus';
import { csrfToken, formatDateOnly } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Info, MapPin, Plus, Trash2, Trophy, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface TournamentSummary {
    id: number;
    uuid: string;
    name: string;
    event_date: string;
    status: string;
    location: string | null;
    encounter_size: number;
    encounter_type: string;
    planned_rounds: number;
    season_label: string;
    players_count: number;
    creator: { id: number; name: string };
}

defineProps<{
    my_tournaments: TournamentSummary[];
    public_tournaments: TournamentSummary[];
}>();

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);
const canCreateTournaments = computed(() => (page.props.auth.permissions ?? []).includes('create_tournaments'));
// Super-admins see a trash icon on every tournament card and can delete in
// any state (creators are still gated to Draft on the controller side).
const isSuperAdmin = computed(() => !!page.props.auth.is_super_admin);

const { statusColor, publicStatusLabel: statusLabel } = useTournamentStatus();

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
                    <Badge class="border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 dark:text-amber-400"
                        >Beta</Badge
                    >
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
                        The Tournament Tracker is in beta and access is request-only while we polish it. If you'd like to run an event with it,
                        reach out to an admin or
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
                <Card class="transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg">
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
            <div v-if="isLoggedIn && my_tournaments.length" class="mb-8">
                <h2 class="mb-3 font-semibold">My Tournaments</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="t in my_tournaments"
                        :key="t.id"
                        :href="route('tournaments.manage', t.uuid)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                            <CardContent class="p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <Badge :class="['border-0 text-[10px]', statusColor(t.status)]" variant="outline">{{
                                        statusLabel(t.status)
                                    }}</Badge>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] tabular-nums text-muted-foreground"
                                            >{{ t.encounter_size }}ss
                                            {{ t.encounter_type === 'traditional' ? '' : t.encounter_type?.replace('_', ' ') }}</span
                                        >
                                        <button
                                            v-if="isSuperAdmin"
                                            type="button"
                                            class="rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            :title="`Delete ${t.name}`"
                                            :aria-label="`Delete ${t.name}`"
                                            @click.stop.prevent="removeTournament(t)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-2 flex items-center gap-2">
                                    <Trophy class="size-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">{{ t.name }}</span>
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span class="flex items-center gap-1 tabular-nums"><CalendarDays class="size-3" /> {{ formatDateOnly(t.event_date) }}</span>
                                    <span v-if="t.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ t.location }}</span>
                                    <span class="flex items-center gap-1 tabular-nums"><Users class="size-3" /> {{ t.players_count }} players</span>
                                    <span class="tabular-nums">{{ t.planned_rounds }} rounds</span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <!-- Public Tournaments -->
            <div v-if="public_tournaments.length">
                <h2 class="mb-3 font-semibold">Public Tournaments</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="t in public_tournaments"
                        :key="'pub-' + t.id"
                        :href="route('tournaments.view', t.uuid)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                            <CardContent class="p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <Badge :class="['border-0 text-[10px]', statusColor(t.status)]" variant="outline">{{
                                        statusLabel(t.status)
                                    }}</Badge>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] tabular-nums text-muted-foreground"
                                            >{{ t.encounter_size }}ss
                                            {{ t.encounter_type === 'traditional' ? '' : t.encounter_type?.replace('_', ' ') }}</span
                                        >
                                        <button
                                            v-if="isSuperAdmin"
                                            type="button"
                                            class="rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            :title="`Delete ${t.name}`"
                                            :aria-label="`Delete ${t.name}`"
                                            @click.stop.prevent="removeTournament(t)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-2 flex items-center gap-2">
                                    <Trophy class="size-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">{{ t.name }}</span>
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span class="flex items-center gap-1 tabular-nums"><CalendarDays class="size-3" /> {{ formatDateOnly(t.event_date) }}</span>
                                    <span v-if="t.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ t.location }}</span>
                                    <span class="flex items-center gap-1 tabular-nums"><Users class="size-3" /> {{ t.players_count }} players</span>
                                    <span>by {{ t.creator.name }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <!-- Empty state -->
            <EmptyState
                v-if="!my_tournaments.length && !public_tournaments.length"
                :icon="Trophy"
                title="No tournaments yet"
                :description="canCreateTournaments
                    ? 'Create your first Gaining Grounds event to get started.'
                    : 'Tournament creation is restricted. Ask an admin for the Tournament Organizer role.'"
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
