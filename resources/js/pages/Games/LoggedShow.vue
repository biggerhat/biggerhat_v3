<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The summary of a game that was played elsewhere and entered by hand. It has no play screen: this is all of it. */
interface Player {
    slot: number;
    name: string;
    profile: string | null;
    faction: string | null;
    master: string | null;
    role: string | null;
    total_points: number;
    soulstone_pool: number;
    link_status: string | null;
}
const props = defineProps<{
    game: {
        uuid: string;
        name: string | null;
        format: string;
        format_label: string;
        played_on: string | null;
        play_mode: string | null;
        season: string;
        encounter_size: number;
        strategy: string | null;
        deployment: string | null;
        notes: string | null;
        venue: string | null;
        is_public: boolean;
        is_tie: boolean;
        winner_slot: number | null;
        logged_by: string;
    };
    players: Player[];
    turns: {
        turn: number;
        slot: number | null;
        strategy_points: number;
        scheme_points: number;
        scheme: string | null;
        scheme_action: string | null;
        notes: string | null;
    }[];
    crews: { slot: number | null; name: string; station: string | null; cost: number | null; killed: boolean }[];
    /** The viewer's own battle report about this game (its id), or the way to start one. Null when the viewer was not a player. */
    battle_report: { uuid: string | null; game_uuid: string } | null;
    can_edit: boolean;
    can_confirm: boolean;
    is_guest: boolean;
}>();

const confirm = useConfirm();
const titleText = computed(() => props.game.name || props.players.map((p) => p.name).join(' vs '));
const winner = computed(() => (props.game.is_tie ? null : props.players.find((p) => p.slot === props.game.winner_slot)));
const factionLabel = (v: string | null) => (v ? v.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '–');
const turnNumbers = computed(() => [...new Set(props.turns.map((t) => t.turn))].sort((a, b) => a - b));
const turnFor = (turn: number, slot: number) => props.turns.find((t) => t.turn === turn && t.slot === slot);
const crewFor = (slot: number) => props.crews.filter((c) => c.slot === slot);
const linkNote = (p: Player) =>
    p.link_status === 'pending'
        ? 'waiting to confirm'
        : p.link_status === 'confirmed'
          ? 'confirmed'
          : p.link_status === 'declined'
            ? 'declined'
            : null;

async function remove() {
    if (
        !(await confirm({
            title: 'Delete this game?',
            message: 'It is removed from your stats and cannot be brought back.',
            confirmLabel: 'Delete',
            destructive: true,
        }))
    )
        return;
    router.delete(route('games.logged.destroy', props.game.uuid));
}
const confirmLink = () => router.post(route('games.logged.confirm', props.game.uuid));
const declineLink = () => router.post(route('games.logged.decline', props.game.uuid));
</script>

<template>
    <Head :title="`Logged game: ${titleText}`" />
    <PageBanner :title="titleText">
        <template #subtitle>
            <div class="my-auto flex flex-wrap items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                <Badge variant="outline">Logged by hand</Badge>
                <span v-if="game.played_on">{{ game.played_on }}</span>
                <span>· logged by {{ game.logged_by }}</span>
            </div>
        </template>
        <template v-if="can_edit || battle_report" #actions>
            <div class="flex flex-wrap gap-2 px-2 pb-2 sm:pb-0">
                <Button v-if="battle_report" as-child size="sm" variant="outline" class="max-sm:min-h-11" data-testid="logged-report">
                    <Link
                        :href="
                            battle_report.uuid
                                ? route('battle-reports.show', battle_report.uuid)
                                : route('battle-reports.create', { game: battle_report.game_uuid })
                        "
                        >{{ battle_report.uuid ? 'Battle report' : 'Write a battle report' }}</Link
                    >
                </Button>
                <template v-if="can_edit">
                    <Button as-child size="sm" variant="outline" class="max-sm:min-h-11" data-testid="logged-edit">
                        <Link :href="route('games.logged.edit', game.uuid)">Edit</Link>
                    </Button>
                    <Button size="sm" variant="outline" class="text-destructive max-sm:min-h-11" data-testid="logged-delete" @click="remove"
                        >Delete</Button
                    >
                </template>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-3xl space-y-4 px-4 pb-8 sm:px-6" data-testid="logged-show">
        <Card v-if="can_confirm" class="border-primary/40" data-testid="logged-confirm">
            <CardContent class="space-y-2 p-4">
                <p class="font-semibold">{{ game.logged_by }} logged this game against you.</p>
                <p class="text-sm text-muted-foreground">Confirm it was you and it counts in your stats. Decline and it is never added.</p>
                <div class="flex flex-wrap gap-2">
                    <Button class="max-sm:min-h-11" data-testid="logged-confirm-yes" @click="confirmLink">It was me</Button>
                    <Button variant="outline" class="max-sm:min-h-11" data-testid="logged-confirm-no" @click="declineLink">Not me</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-3 p-4">
                <p class="text-lg font-semibold" data-testid="logged-result">
                    <template v-if="game.is_tie">A tie</template>
                    <template v-else-if="winner">{{ winner.name }} won</template>
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="p in players"
                        :key="p.slot"
                        class="rounded-md border p-3"
                        :class="winner?.slot === p.slot ? 'border-primary/50 bg-primary/5' : ''"
                    >
                        <p class="font-semibold">
                            <Link v-if="p.profile" :href="route('stats.show', p.profile)" class="hover:underline">{{ p.name }}</Link>
                            <span v-else>{{ p.name }}</span>
                            <span v-if="linkNote(p)" class="ml-1 text-2xs font-normal text-muted-foreground">({{ linkNote(p) }})</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ factionLabel(p.faction) }}<span v-if="p.master"> · {{ p.master }}</span>
                        </p>
                        <p v-if="p.role" class="text-xs capitalize text-muted-foreground">{{ p.role }}</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums">
                            {{ p.total_points }}<span class="text-sm font-normal text-muted-foreground"> VP</span>
                        </p>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-2xs uppercase text-muted-foreground">Format</dt>
                        <dd>{{ game.format_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase text-muted-foreground">Size</dt>
                        <dd>{{ game.encounter_size }}ss</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase text-muted-foreground">Season</dt>
                        <dd>{{ game.season }}</dd>
                    </div>
                    <div v-if="game.strategy">
                        <dt class="text-2xs uppercase text-muted-foreground">Strategy</dt>
                        <dd>{{ game.strategy }}</dd>
                    </div>
                    <div v-if="game.deployment">
                        <dt class="text-2xs uppercase text-muted-foreground">Deployment</dt>
                        <dd>{{ game.deployment }}</dd>
                    </div>
                    <div v-if="game.play_mode">
                        <dt class="text-2xs uppercase text-muted-foreground">How played</dt>
                        <dd>{{ game.play_mode }}</dd>
                    </div>
                    <div v-if="game.venue">
                        <dt class="text-2xs uppercase text-muted-foreground">Event or venue</dt>
                        <dd>{{ game.venue }}</dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase text-muted-foreground">Visible to</dt>
                        <dd>
                            {{
                                game.is_public
                                    ? 'Anyone with the link'
                                    : 'Only you' + (players.some((p) => p.link_status) ? ' and the invited player' : '')
                            }}
                        </dd>
                    </div>
                </dl>
                <p v-if="game.notes" class="whitespace-pre-line rounded-md bg-muted/40 p-3 text-sm" data-testid="logged-notes">{{ game.notes }}</p>
            </CardContent>
        </Card>

        <Card v-if="turnNumbers.length" data-testid="logged-turns">
            <CardContent class="space-y-2 p-4">
                <h2 class="font-semibold">Turn by turn</h2>
                <div class="overflow-x-auto">
                    <Table class="text-xs">
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">Turn</TableHead>
                                <template v-for="p in players" :key="p.slot">
                                    <TableHead scope="col" class="text-right">{{ p.name }}: strategy</TableHead>
                                    <TableHead scope="col" class="text-right">scheme</TableHead>
                                    <TableHead scope="col" class="hidden sm:table-cell">scheme played</TableHead>
                                </template>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="n in turnNumbers" :key="n">
                                <TableCell>{{ n }}</TableCell>
                                <template v-for="p in players" :key="p.slot">
                                    <TableCell class="text-right tabular-nums">{{ turnFor(n, p.slot)?.strategy_points ?? 0 }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ turnFor(n, p.slot)?.scheme_points ?? 0 }}</TableCell>
                                    <TableCell class="hidden sm:table-cell">
                                        {{ turnFor(n, p.slot)?.scheme ?? ''
                                        }}<span v-if="turnFor(n, p.slot)?.scheme_action" class="text-muted-foreground">
                                            ({{ turnFor(n, p.slot)?.scheme_action }})</span
                                        >
                                    </TableCell>
                                </template>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card v-if="crews.length" data-testid="logged-crews">
            <CardContent class="space-y-2 p-4">
                <h2 class="font-semibold">Crews</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="p in players" :key="p.slot">
                        <p class="mb-1 text-sm font-medium">{{ p.name }}</p>
                        <ul class="space-y-0.5 text-sm">
                            <li v-for="(c, i) in crewFor(p.slot)" :key="i" class="flex items-center justify-between gap-2">
                                <span :class="c.killed ? 'text-muted-foreground line-through' : ''">{{ c.name }}</span>
                                <span class="text-xs text-muted-foreground"
                                    >{{ c.killed ? 'killed' : '' }}<span v-if="c.cost != null"> {{ c.cost }}ss</span></span
                                >
                            </li>
                            <li v-if="!crewFor(p.slot).length" class="text-xs text-muted-foreground">No crew recorded.</li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>

        <p class="text-xs text-muted-foreground">
            <Link :href="route('games.index')" class="underline">Back to games</Link>
            <template v-if="!is_guest"> · <Link :href="route('stats.my')" class="underline">Your stats</Link></template>
        </p>
    </div>
</template>
