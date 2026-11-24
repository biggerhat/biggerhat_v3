<script setup lang="ts">
import HelpButton from '@/components/Help/HelpButton.vue';
import CharacterSearch, { type FoundCharacter } from '@/components/Games/CharacterSearch.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

/**
 * Log a game played and tracked somewhere else. The top of the page is the quick log (who won, the points, each side's faction and
 * master); the sections under it are the same things the Game Tracker records (scenario, turns, schemes, crews) and are optional.
 * The same page edits a logged game. The server (App\Http\Requests\Games\Logged) is the judge of what is valid; this only helps.
 */
interface Option {
    value: string;
    label: string;
}
interface ModelRow {
    character_id: number;
    name: string;
    killed: boolean;
    cost: number | null;
    hiring_category?: string | null;
}
interface Side {
    faction: string;
    master_id: number | null;
    master_name: string;
    role: string;
    total_points: number | string;
    soulstone_pool: number | string;
    crew_build_id: number | null;
    opponent_name: string;
    linked_user_id: number | null;
    linked_user_name: string;
    models: ModelRow[];
}
interface TurnSide {
    strategy_points: number | string;
    scheme_points: number | string;
    scheme_id: number | null;
    scheme_action: string;
    strategy_bonus_used: boolean;
    notes: string;
}
interface Turn {
    turn_number: number;
    me: TurnSide;
    opponent: TurnSide;
}
interface SavedCrew {
    id: number;
    name: string;
    faction: string;
    master: { id: number; display_name: string } | null;
    models: { character_id: number; name: string; cost: number | null; station: string | null }[];
}
const props = defineProps<{
    options: {
        formats: { value: string; label: string; default_encounter_size: number; uses_scenario: boolean }[];
        seasons: Option[];
        default_season: string;
        play_modes: Option[];
        factions: Option[];
        deployments: Option[];
        roles: Option[];
        strategies: { id: number; name: string }[];
        schemes: { id: number; name: string }[];
        crew_builds: SavedCrew[];
        today: string;
    };
    /** Present when editing a logged game. */
    game: Record<string, any> | null;
}>();

const NONE = '__none';
const editing = computed(() => props.game !== null);
const fetchLatest = useLatestFetch();

const blankSide = (): Side => ({
    faction: '',
    master_id: null,
    master_name: '',
    role: NONE,
    total_points: '',
    soulstone_pool: '',
    crew_build_id: null,
    opponent_name: '',
    linked_user_id: null,
    linked_user_name: '',
    models: [],
});
const blankTurnSide = (): TurnSide => ({
    strategy_points: '',
    scheme_points: '',
    scheme_id: null,
    scheme_action: NONE,
    strategy_bonus_used: false,
    notes: '',
});
const sideFrom = (s: Record<string, any>): Side => ({
    ...blankSide(),
    faction: s.faction ?? '',
    master_id: s.master_id ?? null,
    master_name: s.master_name ?? '',
    role: s.role ?? NONE,
    total_points: s.total_points ?? '',
    soulstone_pool: s.soulstone_pool || '',
    crew_build_id: s.crew_build_id ?? null,
    opponent_name: s.opponent_name ?? '',
    linked_user_id: s.linked_user_id ?? null,
    linked_user_name: s.linked_user_name ?? '',
    models: (s.models ?? []).map((m: ModelRow) => ({ ...m })),
});
const turnSideFrom = (t: Record<string, any>): TurnSide => ({
    strategy_points: t.strategy_points ?? '',
    scheme_points: t.scheme_points ?? '',
    scheme_id: t.scheme_id ?? null,
    scheme_action: t.scheme_action ?? NONE,
    strategy_bonus_used: !!t.strategy_bonus_used,
    notes: t.notes ?? '',
});

const g = props.game;
const form = reactive({
    format: g?.format ?? 'standard',
    played_on: g?.played_on ?? props.options.today,
    result: (g?.result ?? '') as '' | 'win' | 'loss' | 'tie',
    encounter_size: g?.encounter_size ?? 50,
    season: g?.season ?? props.options.default_season,
    name: g?.name ?? '',
    play_mode: g?.play_mode ?? NONE,
    strategy_id: g?.strategy_id ?? null,
    deployment: g?.deployment ?? NONE,
    scheme_pool: (g?.scheme_pool ?? []) as number[],
    venue: g?.venue ?? '',
    notes: g?.notes ?? '',
    is_public: !!g?.is_public,
    me: g ? sideFrom(g.me) : blankSide(),
    opponent: g ? sideFrom(g.opponent) : blankSide(),
    turns: (g?.turns ?? []).map(
        (t: Record<string, any>): Turn => ({ turn_number: t.turn_number, me: turnSideFrom(t.me), opponent: turnSideFrom(t.opponent) }),
    ) as Turn[],
});

const format = computed(() => props.options.formats.find((f) => f.value === form.format));
const usesScenario = computed(() => format.value?.uses_scenario ?? true);
watch(
    () => form.format,
    (v, old) => {
        const was = props.options.formats.find((f) => f.value === old)?.default_encounter_size;
        const now = props.options.formats.find((f) => f.value === v)?.default_encounter_size;
        // follow the format's usual size unless the player already chose another
        if (now && Number(form.encounter_size) === was) form.encounter_size = now;
    },
);

// ── points: the turns decide them when any are entered ──
const n = (v: number | string) => (v === '' || v === null ? 0 : Number(v) || 0);
const sumTurns = (who: 'me' | 'opponent') => form.turns.reduce((t, x) => t + n(x[who].strategy_points) + n(x[who].scheme_points), 0);
const hasTurnPoints = computed(() => form.turns.some((t) => [t.me, t.opponent].some((s) => s.strategy_points !== '' || s.scheme_points !== '')));
watch(
    [() => form.turns, hasTurnPoints],
    () => {
        if (!hasTurnPoints.value) return;
        form.me.total_points = sumTurns('me');
        form.opponent.total_points = sumTurns('opponent');
    },
    { deep: true },
);
const disagrees = computed(() => {
    const a = n(form.me.total_points);
    const b = n(form.opponent.total_points);
    if (form.me.total_points === '' || form.opponent.total_points === '' || !form.result) return null;
    if (form.result === 'win' && a < b)
        return 'You marked a win with fewer points than your opponent. That is fine for a concession, otherwise check the result.';
    if (form.result === 'loss' && a > b)
        return 'You marked a loss with more points than your opponent. That is fine for a concession, otherwise check the result.';
    if (form.result === 'tie' && a !== b) return 'A tie with different points. Check the result or the points.';
    return null;
});

// ── sections ──
const open = reactive({
    scenario: !!(g && (g.strategy_id || g.deployment || (g.scheme_pool ?? []).length)),
    turns: !!(g && (g.turns ?? []).length),
    crews: !!(g && (g.me.models.length || g.opponent.models.length)),
    extras: !!(g && (g.venue || g.notes || g.is_public || g.play_mode)),
});

function addTurn() {
    const next = form.turns.length ? Math.max(...form.turns.map((t) => t.turn_number)) + 1 : 1;
    if (next > 8) return;
    form.turns.push({ turn_number: next, me: blankTurnSide(), opponent: blankTurnSide() });
}
const removeTurn = (i: number) => form.turns.splice(i, 1);

// ── crews ──
function useSavedCrew(id: string) {
    const crew = props.options.crew_builds.find((c) => String(c.id) === id);
    if (!crew) {
        form.me.crew_build_id = null;
        return;
    }
    form.me.crew_build_id = crew.id;
    if (!form.me.faction) form.me.faction = crew.faction;
    if (crew.master && !form.me.master_name) {
        form.me.master_id = crew.master.id;
        form.me.master_name = crew.master.display_name;
    }
    form.me.models = crew.models.map((m) => ({ character_id: m.character_id, name: m.name, killed: false, cost: m.cost, hiring_category: null }));
}
function addModel(who: 'me' | 'opponent', c: FoundCharacter) {
    form[who].models.push({ character_id: c.id, name: c.name, killed: false, cost: c.cost, hiring_category: null });
}
const removeModel = (who: 'me' | 'opponent', i: number) => form[who].models.splice(i, 1);
function pickMaster(who: 'me' | 'opponent', c: FoundCharacter) {
    form[who].master_id = c.id;
    form[who].master_name = c.name;
}
function typedMaster(who: 'me' | 'opponent', text: string) {
    // typing again after a pick means it is no longer that catalogue model
    if (text !== form[who].master_name) form[who].master_id = null;
    form[who].master_name = text;
}

// ── opponent account ──
const userQuery = ref('');
const userResults = ref<{ id: number; name: string }[]>([]);
let userTimer: ReturnType<typeof setTimeout> | undefined;
function searchUsers() {
    clearTimeout(userTimer);
    if (userQuery.value.trim().length < 2) {
        userResults.value = [];
        return;
    }
    userTimer = setTimeout(async () => {
        const data = await fetchLatest<{ users: { id: number; name: string }[] }>('users', route('users.search', { q: userQuery.value.trim() }));
        if (data) userResults.value = data.users;
    }, 250);
}
function linkUser(u: { id: number; name: string }) {
    form.opponent.linked_user_id = u.id;
    form.opponent.linked_user_name = u.name;
    if (!form.opponent.opponent_name) form.opponent.opponent_name = u.name;
    userQuery.value = '';
    userResults.value = [];
}
function unlinkUser() {
    form.opponent.linked_user_id = null;
    form.opponent.linked_user_name = '';
}

// ── scheme pool ──
const schemeFilter = ref('');
const shownSchemes = computed(() =>
    props.options.schemes.filter((s) => s.name.toLowerCase().includes(schemeFilter.value.toLowerCase())).slice(0, 40),
);
const schemeName = (id: number) => props.options.schemes.find((s) => s.id === id)?.name ?? '';
function togglePoolScheme(id: number) {
    form.scheme_pool = form.scheme_pool.includes(id)
        ? form.scheme_pool.filter((x) => x !== id)
        : form.scheme_pool.length < 10
          ? [...form.scheme_pool, id]
          : form.scheme_pool;
}

// ── submit ──
const errors = ref<Record<string, string>>({});
const saving = ref(false);
const orNull = (v: string) => (v === NONE || v === '' ? null : v);

function sidePayload(who: 'me' | 'opponent') {
    const s = form[who];
    return {
        faction: s.faction,
        master_id: s.master_id,
        master_name: s.master_name.trim() || null,
        role: orNull(s.role),
        total_points: n(s.total_points),
        soulstone_pool: s.soulstone_pool === '' ? null : n(s.soulstone_pool),
        models: s.models.map((m) => ({ character_id: m.character_id, killed: m.killed, cost: m.cost, hiring_category: m.hiring_category ?? null })),
        ...(who === 'me' ? { crew_build_id: s.crew_build_id } : { opponent_name: s.opponent_name.trim() || null, linked_user_id: s.linked_user_id }),
    };
}
const turnSidePayload = (t: TurnSide) => ({
    strategy_points: t.strategy_points === '' ? null : n(t.strategy_points),
    scheme_points: t.scheme_points === '' ? null : n(t.scheme_points),
    scheme_id: t.scheme_id,
    scheme_action: orNull(t.scheme_action),
    strategy_bonus_used: t.strategy_bonus_used,
    notes: t.notes.trim() || null,
});

function submit() {
    saving.value = true;
    errors.value = {};
    const payload = {
        format: form.format,
        played_on: form.played_on,
        result: form.result,
        encounter_size: n(form.encounter_size),
        season: form.season,
        name: form.name.trim() || null,
        play_mode: orNull(form.play_mode),
        strategy_id: usesScenario.value ? form.strategy_id : null,
        deployment: usesScenario.value ? orNull(form.deployment) : null,
        scheme_pool: usesScenario.value ? form.scheme_pool : [],
        venue: form.venue.trim() || null,
        notes: form.notes.trim() || null,
        is_public: form.is_public,
        me: sidePayload('me'),
        opponent: sidePayload('opponent'),
        turns: form.turns.map((t) => ({ turn_number: t.turn_number, me: turnSidePayload(t.me), opponent: turnSidePayload(t.opponent) })),
    };
    const opts = {
        preserveScroll: true,
        onError: (e: Record<string, string>) => {
            errors.value = e;
            // open the section an error is in, so it is not hidden
            const keys = Object.keys(e);
            if (keys.some((k) => k.startsWith('turns'))) open.turns = true;
            if (keys.some((k) => /models|crew_build/.test(k))) open.crews = true;
            if (keys.some((k) => /strategy_id|deployment|scheme_pool/.test(k))) open.scenario = true;
            if (keys.some((k) => /venue|notes|play_mode|is_public/.test(k))) open.extras = true;
            document.getElementById('log-errors')?.scrollIntoView({ block: 'center' });
        },
        onFinish: () => (saving.value = false),
    };
    if (editing.value) router.put(route('games.logged.update', props.game!.uuid), payload, opts);
    else router.post(route('games.logged.store'), payload, opts);
}
const errorList = computed(() => Object.entries(errors.value));
const canSubmit = computed(
    () =>
        !saving.value &&
        !!form.result &&
        !!form.me.faction &&
        !!form.opponent.faction &&
        form.me.total_points !== '' &&
        form.opponent.total_points !== '',
);
const resultOptions = [
    { value: 'win', label: 'I won' },
    { value: 'loss', label: 'I lost' },
    { value: 'tie', label: 'A tie' },
];
</script>

<template>
    <Head :title="editing ? 'Edit logged game' : 'Log a game'" />
    <PageBanner :title="editing ? 'Edit logged game' : 'Log a game'">
        <template #subtitle>
            <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                A game you played and tracked somewhere else. It counts in your stats like any other.
                <HelpButton topic="game-tracker" />
            </div>
        </template>
    </PageBanner>

    <form class="container mx-auto max-w-3xl space-y-4 px-4 pb-24 sm:px-6" data-testid="log-form" novalidate @submit.prevent="submit">
        <div
            v-if="errorList.length"
            id="log-errors"
            role="alert"
            class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm"
            data-testid="log-errors"
        >
            <p class="font-medium">Check these:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="[k, m] in errorList" :key="k">{{ m }}</li>
            </ul>
        </div>

        <!-- the quick log -->
        <Card>
            <CardContent class="space-y-4 p-4">
                <h2 class="font-semibold">The game</h2>
                <div class="space-y-1.5">
                    <Label for="log-name">Name this game (optional)</Label>
                    <Input
                        id="log-name"
                        v-model="form.name"
                        type="text"
                        maxlength="255"
                        placeholder="League round 3"
                        autocomplete="off"
                        data-testid="log-name"
                    />
                    <p class="text-xs text-muted-foreground">
                        Just for you: it is what the game is called in your lists. Left empty, it is shown as the two players.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="log-date">Day played</Label>
                        <Input id="log-date" v-model="form.played_on" type="date" :max="options.today" data-testid="log-date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="log-format">Format</Label>
                        <Select v-model="form.format">
                            <SelectTrigger id="log-format" data-testid="log-format"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="f in options.formats" :key="f.value" :value="f.value">{{ f.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="log-size">Encounter size (ss)</Label>
                        <Input
                            id="log-size"
                            v-model="form.encounter_size"
                            type="number"
                            min="10"
                            max="100"
                            inputmode="numeric"
                            data-testid="log-size"
                        />
                    </div>
                </div>

                <fieldset class="space-y-1.5">
                    <legend class="text-sm font-medium">Who won?</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <Button
                            v-for="r in resultOptions"
                            :key="r.value"
                            type="button"
                            :variant="form.result === r.value ? 'default' : 'outline'"
                            :aria-pressed="form.result === r.value"
                            class="min-h-11"
                            :data-testid="`log-result-${r.value}`"
                            @click="form.result = r.value as 'win' | 'loss' | 'tie'"
                        >
                            {{ r.label }}
                        </Button>
                    </div>
                    <p v-if="disagrees" class="text-xs text-amber-700 dark:text-amber-400" role="status" data-testid="log-disagree">
                        {{ disagrees }}
                    </p>
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- me -->
                    <div class="space-y-3 rounded-md border p-3" data-testid="log-me">
                        <h3 class="text-sm font-semibold">You</h3>
                        <div class="space-y-1.5">
                            <Label for="me-faction">Faction</Label>
                            <Select v-model="form.me.faction">
                                <SelectTrigger id="me-faction" data-testid="me-faction"><SelectValue placeholder="Choose" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="f in options.factions" :key="f.value" :value="f.value">{{ f.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <CharacterSearch
                            id="me-master"
                            label="Master"
                            masters
                            :faction="form.me.faction || null"
                            :model-value="form.me.master_name"
                            free
                            @pick="pickMaster('me', $event)"
                            @update:model-value="typedMaster('me', $event)"
                        />
                        <div class="space-y-1.5">
                            <Label for="me-role">Attacker or defender</Label>
                            <Select v-model="form.me.role">
                                <SelectTrigger id="me-role" data-testid="me-role"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">Not recorded</SelectItem>
                                    <SelectItem v-for="r in options.roles" :key="r.value" :value="r.value">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="me-vp">Victory points</Label>
                            <Input
                                id="me-vp"
                                v-model="form.me.total_points"
                                type="number"
                                min="0"
                                max="99"
                                inputmode="numeric"
                                :readonly="hasTurnPoints"
                                data-testid="me-vp"
                            />
                            <p v-if="hasTurnPoints" class="text-2xs text-muted-foreground">Added up from the turns below.</p>
                        </div>
                    </div>
                    <!-- opponent -->
                    <div class="space-y-3 rounded-md border p-3" data-testid="log-opponent">
                        <h3 class="text-sm font-semibold">Opponent</h3>
                        <div class="space-y-1.5">
                            <Label for="opp-name">Name</Label>
                            <Input id="opp-name" v-model="form.opponent.opponent_name" type="text" maxlength="80" data-testid="opp-name" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="opp-faction">Faction</Label>
                            <Select v-model="form.opponent.faction">
                                <SelectTrigger id="opp-faction" data-testid="opp-faction"><SelectValue placeholder="Choose" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="f in options.factions" :key="f.value" :value="f.value">{{ f.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <CharacterSearch
                            id="opp-master"
                            label="Master"
                            masters
                            :faction="form.opponent.faction || null"
                            :model-value="form.opponent.master_name"
                            free
                            @pick="pickMaster('opponent', $event)"
                            @update:model-value="typedMaster('opponent', $event)"
                        />
                        <div class="space-y-1.5">
                            <Label for="opp-role">Attacker or defender</Label>
                            <Select v-model="form.opponent.role">
                                <SelectTrigger id="opp-role" data-testid="opp-role"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">Not recorded</SelectItem>
                                    <SelectItem v-for="r in options.roles" :key="r.value" :value="r.value">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="opp-vp">Victory points</Label>
                            <Input
                                id="opp-vp"
                                v-model="form.opponent.total_points"
                                type="number"
                                min="0"
                                max="99"
                                inputmode="numeric"
                                :readonly="hasTurnPoints"
                                data-testid="opp-vp"
                            />
                        </div>
                        <!-- an account to link -->
                        <div class="space-y-1.5 border-t pt-3">
                            <Label for="opp-user">Link their BiggerHat account (optional)</Label>
                            <div
                                v-if="form.opponent.linked_user_id"
                                class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-2 py-1.5 text-sm"
                                data-testid="opp-linked"
                            >
                                <span>{{ form.opponent.linked_user_name }}</span>
                                <Button type="button" variant="ghost" size="sm" class="max-sm:min-h-11" @click="unlinkUser">Remove</Button>
                            </div>
                            <div v-else class="relative">
                                <Input
                                    id="opp-user"
                                    v-model="userQuery"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="Search by name"
                                    data-testid="opp-user"
                                    @input="searchUsers"
                                />
                                <ul
                                    v-if="userResults.length"
                                    class="absolute z-20 mt-1 w-full rounded-md border bg-popover p-1 text-sm shadow-md"
                                    role="listbox"
                                >
                                    <li v-for="u in userResults" :key="u.id" role="presentation">
                                        <button
                                            type="button"
                                            role="option"
                                            :aria-selected="false"
                                            class="flex min-h-11 w-full items-center rounded px-2 text-left hover:bg-accent sm:min-h-8"
                                            @mousedown.prevent="linkUser(u)"
                                        >
                                            {{ u.name }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <p class="text-2xs text-muted-foreground">They are asked to confirm. Until they do, the game is only in your stats.</p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- scenario -->
        <Card v-if="usesScenario" data-testid="log-scenario-card">
            <CardContent class="space-y-3 p-4">
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-left font-semibold"
                    :aria-expanded="open.scenario"
                    data-testid="log-scenario-toggle"
                    @click="open.scenario = !open.scenario"
                >
                    Strategy, deployment and schemes<span class="text-xs font-normal text-muted-foreground"
                        >{{ open.scenario ? 'Hide' : 'Add' }} (optional)</span
                    >
                </button>
                <div v-show="open.scenario" class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label for="log-strategy">Strategy</Label>
                            <Select
                                :model-value="form.strategy_id === null ? NONE : String(form.strategy_id)"
                                @update:model-value="(v) => (form.strategy_id = v === NONE ? null : Number(v))"
                            >
                                <SelectTrigger id="log-strategy" data-testid="log-strategy"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">Not recorded</SelectItem>
                                    <SelectItem v-for="s in options.strategies" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="log-deployment">Deployment</Label>
                            <Select v-model="form.deployment">
                                <SelectTrigger id="log-deployment" data-testid="log-deployment"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">Not recorded</SelectItem>
                                    <SelectItem v-for="d in options.deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="log-season">Season</Label>
                            <Select v-model="form.season">
                                <SelectTrigger id="log-season"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in options.seasons" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="log-scheme-filter">Scheme pool (up to 10)</Label>
                        <div v-if="form.scheme_pool.length" class="flex flex-wrap gap-1.5">
                            <Badge v-for="id in form.scheme_pool" :key="id" variant="secondary" class="gap-1">
                                {{ schemeName(id)
                                }}<button type="button" class="px-1" :aria-label="`Remove ${schemeName(id)}`" @click="togglePoolScheme(id)">×</button>
                            </Badge>
                        </div>
                        <Input id="log-scheme-filter" v-model="schemeFilter" type="search" placeholder="Find a scheme" autocomplete="off" />
                        <ul class="max-h-40 overflow-auto rounded-md border p-1 text-sm">
                            <li v-for="s in shownSchemes" :key="s.id">
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded px-2 hover:bg-accent sm:min-h-8">
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="form.scheme_pool.includes(s.id)"
                                        @change="togglePoolScheme(s.id)"
                                    />{{ s.name }}
                                </label>
                            </li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- turns -->
        <Card data-testid="log-turns-card">
            <CardContent class="space-y-3 p-4">
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-left font-semibold"
                    :aria-expanded="open.turns"
                    data-testid="log-turns-toggle"
                    @click="open.turns = !open.turns"
                >
                    Turn by turn<span class="text-xs font-normal text-muted-foreground">{{ open.turns ? 'Hide' : 'Add' }} (optional)</span>
                </button>
                <div v-show="open.turns" class="space-y-3">
                    <p class="text-xs text-muted-foreground">
                        Points scored each turn. When you fill these in, the totals above are added up for you.
                    </p>
                    <div
                        v-for="(t, i) in form.turns"
                        :key="t.turn_number"
                        class="space-y-2 rounded-md border p-3"
                        :data-testid="`log-turn-${t.turn_number}`"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Turn {{ t.turn_number }}</h3>
                            <Button type="button" variant="ghost" size="sm" class="max-sm:min-h-11" @click="removeTurn(i)">Remove</Button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div v-for="who in ['me', 'opponent'] as const" :key="who" class="space-y-2">
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ who === 'me' ? 'You' : form.opponent.opponent_name || 'Opponent' }}
                                </p>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="space-y-1">
                                        <Label :for="`t${t.turn_number}-${who}-strat`" class="text-xs">Strategy VP</Label>
                                        <Input
                                            :id="`t${t.turn_number}-${who}-strat`"
                                            v-model="t[who].strategy_points"
                                            type="number"
                                            min="0"
                                            max="20"
                                            inputmode="numeric"
                                            :data-testid="`t${t.turn_number}-${who}-strategy`"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label :for="`t${t.turn_number}-${who}-scheme`" class="text-xs">Scheme VP</Label>
                                        <Input
                                            :id="`t${t.turn_number}-${who}-scheme`"
                                            v-model="t[who].scheme_points"
                                            type="number"
                                            min="0"
                                            max="20"
                                            inputmode="numeric"
                                            :data-testid="`t${t.turn_number}-${who}-schemevp`"
                                        />
                                    </div>
                                </div>
                                <Select
                                    :model-value="t[who].scheme_id === null ? NONE : String(t[who].scheme_id)"
                                    @update:model-value="(v) => (t[who].scheme_id = v === NONE ? null : Number(v))"
                                >
                                    <SelectTrigger :aria-label="`Scheme, turn ${t.turn_number}, ${who === 'me' ? 'you' : 'opponent'}`"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE">No scheme recorded</SelectItem>
                                        <SelectItem v-for="s in options.schemes" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="t[who].scheme_action">
                                    <SelectTrigger
                                        :aria-label="`What happened to the scheme, turn ${t.turn_number}, ${who === 'me' ? 'you' : 'opponent'}`"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE">What happened to it</SelectItem>
                                        <SelectItem value="scored">Scored</SelectItem>
                                        <SelectItem value="held">Held for later</SelectItem>
                                        <SelectItem value="discarded">Discarded</SelectItem>
                                    </SelectContent>
                                </Select>
                                <label class="flex min-h-11 items-center gap-2 text-xs sm:min-h-0">
                                    <input v-model="t[who].strategy_bonus_used" type="checkbox" class="size-4" />Strategy bonus point taken
                                </label>
                            </div>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="max-sm:min-h-11"
                        :disabled="form.turns.length >= 8"
                        data-testid="log-add-turn"
                        @click="addTurn"
                        >Add a turn</Button
                    >
                </div>
            </CardContent>
        </Card>

        <!-- crews -->
        <Card data-testid="log-crews-card">
            <CardContent class="space-y-3 p-4">
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-left font-semibold"
                    :aria-expanded="open.crews"
                    data-testid="log-crews-toggle"
                    @click="open.crews = !open.crews"
                >
                    Crews<span class="text-xs font-normal text-muted-foreground">{{ open.crews ? 'Hide' : 'Add' }} (optional)</span>
                </button>
                <div v-show="open.crews" class="grid gap-4 sm:grid-cols-2">
                    <div v-for="who in ['me', 'opponent'] as const" :key="who" class="space-y-2">
                        <h3 class="text-sm font-semibold">
                            {{ who === 'me' ? 'Your crew' : `${form.opponent.opponent_name || 'Opponent'}'s crew` }}
                        </h3>
                        <div v-if="who === 'me' && options.crew_builds.length" class="space-y-1.5">
                            <Label for="log-saved-crew">Start from a saved crew</Label>
                            <Select
                                :model-value="form.me.crew_build_id === null ? NONE : String(form.me.crew_build_id)"
                                @update:model-value="(v) => useSavedCrew(String(v))"
                            >
                                <SelectTrigger id="log-saved-crew" data-testid="log-saved-crew"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NONE">None</SelectItem>
                                    <SelectItem v-for="c in options.crew_builds" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <CharacterSearch :id="`${who}-add-model`" label="Add a model" :faction="null" clear-on-pick @pick="addModel(who, $event)" />
                        <ul class="space-y-1 text-sm" :data-testid="`log-models-${who}`">
                            <li
                                v-for="(m, i) in form[who].models"
                                :key="i"
                                class="flex items-center justify-between gap-2 rounded-md border px-2 py-1"
                            >
                                <span class="min-w-0 flex-1 truncate" :class="m.killed ? 'text-muted-foreground line-through' : ''">{{
                                    m.name
                                }}</span>
                                <label class="flex min-h-11 items-center gap-1 text-xs sm:min-h-0"
                                    ><input v-model="m.killed" type="checkbox" class="size-4" />killed</label
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="max-sm:min-h-11"
                                    :aria-label="`Remove ${m.name}`"
                                    @click="removeModel(who, i)"
                                    >×</Button
                                >
                            </li>
                            <li v-if="!form[who].models.length" class="text-xs text-muted-foreground">No models added.</li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- extras -->
        <Card data-testid="log-extras-card">
            <CardContent class="space-y-3 p-4">
                <button
                    type="button"
                    class="flex w-full items-center justify-between text-left font-semibold"
                    :aria-expanded="open.extras"
                    data-testid="log-extras-toggle"
                    @click="open.extras = !open.extras"
                >
                    Notes, event and who can see it<span class="text-xs font-normal text-muted-foreground"
                        >{{ open.extras ? 'Hide' : 'Add' }} (optional)</span
                    >
                </button>
                <div v-show="open.extras" class="space-y-3">
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <Label for="log-venue">Event or venue</Label>
                            <Input
                                id="log-venue"
                                v-model="form.venue"
                                type="text"
                                maxlength="120"
                                placeholder="Friday league"
                                data-testid="log-venue"
                            />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="log-playmode">How it was played</Label>
                        <Select v-model="form.play_mode">
                            <SelectTrigger id="log-playmode"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">Not recorded</SelectItem>
                                <SelectItem v-for="m in options.play_modes" :key="m.value" :value="m.value">{{ m.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="log-notes">Notes</Label>
                        <Textarea id="log-notes" v-model="form.notes" maxlength="5000" rows="3" data-testid="log-notes" />
                    </div>
                    <div class="flex items-start gap-3">
                        <Switch id="log-public" v-model="form.is_public" data-testid="log-public" />
                        <div>
                            <Label for="log-public">Anyone can see this game</Label>
                            <p class="text-xs text-muted-foreground">
                                Off, only you (and the player you link) can open it and it counts only in your own stats. On, it also counts on your
                                public profile.
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div
            class="sticky bottom-14 z-10 -mx-4 flex items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0"
        >
            <Button as-child variant="ghost" class="max-sm:min-h-11"
                ><Link :href="editing ? route('games.logged.show', game!.uuid) : route('games.index')">Cancel</Link></Button
            >
            <Button type="submit" class="min-h-11 sm:min-h-9" :disabled="!canSubmit" data-testid="log-submit">{{
                saving ? 'Saving…' : editing ? 'Save changes' : 'Log game'
            }}</Button>
        </div>
    </form>
</template>
