<script setup lang="ts">
import FateDock from '@/components/Fate/FateDock.vue';
import GameCrewUpgrades from '@/components/Game/GameCrewUpgrades.vue';
import GameReplaceDialog from '@/components/Game/GameReplaceDialog.vue';
import GameReplaceOnDeathDialog from '@/components/Game/GameReplaceOnDeathDialog.vue';
import GameRoundClock, { type RoundClock } from '@/components/Game/GameRoundClock.vue';
import GameTurnBar, { type TurnState } from '@/components/Game/GameTurnBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TabletopBoard from '@/components/Tabletop/TabletopBoard.vue';
import { Button } from '@/components/ui/button';
import { useFateGame } from '@/composables/useFateGame';
import { useGameApi } from '@/composables/useGameApi';
import { useToast } from '@/composables/useToast';
import {
    TABLE_SIZE,
    baseRadius,
    type Aura,
    type ModelInfo,
    type DeploymentKind,
    type MarkerOption,
    type TerrainPiece,
    type Token,
} from '@/lib/tabletop/geometry';
import type { SharedData } from '@/types';
import type { SavedLayout } from '@/lib/tabletop/layouts';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { loadEcho } from '@/lib/echo';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface PlacedMarker {
    id: string;
    marker_id: number;
    /** The slot of the player who placed it: only they move or pick it up. */
    owner: number;
    x: number;
    y: number;
}

/** An aura a player has shown to the other player, as saved with the game. */
interface SharedAura {
    id: string;
    owner_id: string;
    range: number;
    locked: boolean;
    x?: number;
    y?: number;
    traits?: string[];
}

/** What a player has noted about their scheme: private to them. */
interface SchemeNotes {
    note?: string | null;
    selected_model?: string | null;
    selected_marker?: string | null;
    terrain_note?: string | null;
}

interface BoardModel {
    id: number;
    name: string;
    slot: number;
    base_mm: number;
    sz: number | null;
    sp: number | null;
    killed: boolean;
    health: number | null;
    max_health: number | null;
    activated: boolean;
    tokens: { id: number; name: string }[];
    notes: string | null;
    /** What the crew row on the play screen reads (card images, stats, upgrades). */
    member: Record<string, any>;
}

const props = defineProps<{
    game: { uuid: string; is_solo: boolean; deployment: DeploymentKind | null; auto_soulstone_on_kill?: boolean; is_campaign?: boolean };
    /** Someone watching an observable game: nothing can be changed, and the page refreshes itself. */
    read_only?: boolean;
    players: {
        slot: number;
        name: string;
        mine: boolean;
        soulstone_pool?: number;
        scheme_notes?: SchemeNotes | null;
        reference_upgrade_ids?: number[];
    }[];
    /** What can be attached to a model (the play screen's lists); deferred, and empty for a watcher. */
    character_upgrades?: { id: number; name: string; front_image: string | null; back_image: string | null }[];
    character_injuries?: { id: number; name: string; front_image: string | null; back_image: string | null }[];
    /** Initiative, whose activation, what is left to activate, and whether a pass is allowed. */
    turn?: TurnState | null;
    /** The tournament round's clock, for a game in a running round. */
    clock?: RoundClock | null;
    /** Where the models and markers stood at the end of each turn (positions of moved models, and the markers), by turn number. */
    history?: Record<
        string,
        {
            positions: Record<string, { x: number; y: number; under?: boolean; ignores?: string[] }>;
            markers: PlacedMarker[];
            /** How each model stood: health, whether it was killed, its tokens. */
            models?: Record<string, { health: number | null; max_health: number | null; killed: boolean; tokens: { id: number; name: string }[] }>;
        }
    >;
    models: BoardModel[];
    positions: Record<string, { x: number; y: number; under?: boolean; ignores?: string[] }>;
    terrain: TerrainPiece[];
    markers: MarkerOption[];
    placed_markers: PlacedMarker[];
    shared_auras: SharedAura[];
    token_options: { id: number; name: string }[];
    deployment_variant: number;
    table_image: string | null;
    table_images: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
    terrain_images: Record<string, string>;
    /** The viewer's saved terrain layouts. */
    layouts?: SavedLayout[];
}>();
const page = usePage<SharedData>();

// Saved positions, plus moves the other player has just made (applied straight from the broadcast).
const live = ref<Record<string, { x: number; y: number; under?: boolean; ignores?: string[] }>>({ ...props.positions });
watch(
    () => props.positions,
    (next) => (live.value = { ...next }),
);

// The table layout: saved with the game, and replaced whole when the other player changes it.
const liveTerrain = ref<TerrainPiece[]>(props.terrain);
watch(
    () => props.terrain,
    (next) => (liveTerrain.value = next),
);

// Markers on the table, shared by both players. Name and base size always come from the markers table.
const placed = ref<PlacedMarker[]>(props.placed_markers);
watch(
    () => props.placed_markers,
    (next) => (placed.value = next),
);

// Auras either player chose to show both sides. The rest stay private to each browser.
const toAura = (a: SharedAura): Aura => ({ id: a.id, ownerId: a.owner_id, range: a.range, locked: a.locked, x: a.x, y: a.y, traits: a.traits });
const sharedAuras = ref<Aura[]>(props.shared_auras.map(toAura));
watch(
    () => props.shared_auras,
    (next) => (sharedAuras.value = next.map(toAura)),
);

// Which way round the deployment zones sit, agreed by both players.
const variant = ref(props.deployment_variant);
watch(
    () => props.deployment_variant,
    (next) => (variant.value = next),
);
function variantChanged(next: number) {
    variant.value = next;
    if (props.read_only) return;
    router.put(
        route('games.board.setup', props.game.uuid),
        { deployment_variant: next },
        { preserveScroll: true, preserveState: true, only: ['deployment_variant'] },
    );
}

// The table surface, agreed by both players like the deployment orientation.
const tableImage = ref<string | null>(props.table_image);
watch(
    () => props.table_image,
    (next) => (tableImage.value = next),
);
function tableImageChanged(next: string | null) {
    tableImage.value = next;
    if (props.read_only) return;
    router.put(
        route('games.board.setup', props.game.uuid),
        { table_image: next },
        { preserveScroll: true, preserveState: true, only: ['table_image'] },
    );
}

let modelsTimer: ReturnType<typeof setTimeout> | null = null;
let joined = false;
let listening: { stopListening: (e: string) => unknown } | null = null;
const resync = () =>
    document.visibilityState === 'visible' &&
    router.reload({
        only: ['positions', 'terrain', 'placed_markers', 'shared_auras', 'deployment_variant', 'table_image', 'models', 'turn', 'clock'],
    });

let poll: ReturnType<typeof setInterval> | null = null;
onMounted(async () => {
    document.addEventListener('visibilitychange', resync);
    // Load the Fate deck a moment after the table is up (not before it), so a flip made for a model shows beside it without opening the deck.
    // A watcher polls already, so only the players do this.
    if (!props.read_only) window.setTimeout(() => void fate.start(), 1200);
    const echo = await loadEcho();
    if (!echo) {
        // no socket: look again every few seconds while the tab is showing
        if (props.read_only) poll = setInterval(resync, 8000);
        return;
    }
    // Players join the game's channel; people watching hear the same board changes on its public channel (an observable game only).
    const channel = props.read_only ? echo.channel(`game-observe.${props.game.uuid}`) : echo.join(`game.${props.game.uuid}`);
    joined = true;
    listening = channel;
    channel
        .listen('.GameBoardMoved', (e: { positions: Record<string, { x: number; y: number; under?: boolean; ignores?: string[] }> }) => {
            live.value = { ...live.value, ...e.positions };
        })
        .listen('.GameBoardTerrainChanged', (e: { terrain: TerrainPiece[] }) => {
            liveTerrain.value = e.terrain;
        })
        .listen('.GameBoardMarkersChanged', (e: { markers: PlacedMarker[] }) => {
            placed.value = e.markers;
        })
        .listen('.GameCrewMemberUpdated', () => {
            // the play screen changed a model (health, tokens, a kill, the turn order): look again, once if several arrive together
            if (modelsTimer) clearTimeout(modelsTimer);
            modelsTimer = setTimeout(() => router.reload({ only: ['models', 'turn'] }), 400);
        })
        .listen('.GameBoardSetupChanged', (e: { deployment_variant: number; table_image: string | null }) => {
            variant.value = e.deployment_variant;
            tableImage.value = e.table_image;
        })
        .listen('.GameBoardAurasChanged', (e: { auras: SharedAura[] }) => {
            sharedAuras.value = e.auras.map(toAura);
        });
    // a safety net for a watcher whose socket drops quietly
    if (props.read_only) poll = setInterval(resync, 30000);
});
onUnmounted(() => {
    document.removeEventListener('visibilitychange', resync);
    if (poll) clearInterval(poll);
    if (modelsTimer) clearTimeout(modelsTimer);
    if (props.read_only) {
        // the public channel is shared with the Fate deck's listener: take off only ours
        for (const e of [
            '.GameBoardMoved',
            '.GameBoardTerrainChanged',
            '.GameBoardMarkersChanged',
            '.GameCrewMemberUpdated',
            '.GameBoardSetupChanged',
            '.GameBoardAurasChanged',
        ])
            listening?.stopListening(e);
    } else if (joined) window.Echo?.leave(`game.${props.game.uuid}`);
});

// The crew as the game tracker keeps it: health, activation, tokens. Changed here, they are saved through the tracker's own endpoint, so
// the play screen shows the same thing.
const liveModels = ref<BoardModel[]>(props.models.map((m) => ({ ...m })));
watch(
    () => props.models,
    (next) => (liveModels.value = next.map((m) => ({ ...m }))),
    { deep: true },
);
const gameApi = useGameApi();
const toast = useToast();
async function modelChange(id: string, patch: Partial<ModelInfo>) {
    if (props.read_only) return;
    const body: Record<string, unknown> = {};
    if (patch.health !== undefined) body.current_health = patch.health;
    if (patch.activated !== undefined) body.is_activated = patch.activated;
    if (patch.tokens !== undefined) body.attached_tokens = patch.tokens;
    if (patch.notes !== undefined) body.notes = patch.notes;
    if (!Object.keys(body).length) return;
    // show it at once; put it back if the game refuses
    liveModels.value = liveModels.value.map((m) =>
        String(m.id) === id
            ? {
                  ...m,
                  health: patch.health ?? m.health,
                  activated: patch.activated ?? m.activated,
                  tokens: patch.tokens ?? m.tokens,
                  notes: patch.notes !== undefined ? patch.notes : m.notes,
              }
            : m,
    );
    const res = await gameApi.patch<{ error?: string }>(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: id }), body);
    if (!res.ok) {
        toast.error(res.data.error ?? (res.status === 409 || res.status === 422 ? 'The game is not in progress.' : 'That change was not saved.'));
        router.reload({ only: ['models'] });
    }
}

// The crew list on the board: the play screen's rows, fed from the same models (so a change shows in both places).
const crew = computed(() =>
    modelsShown.value.map((m) => ({
        ...m.member,
        id: m.id,
        display_name: m.name,
        slot: m.slot,
        current_health: m.health,
        max_health: m.max_health,
        is_activated: m.activated,
        is_killed: m.killed,
        attached_tokens: m.tokens,
        notes: m.notes,
    })),
);

// Kill a model, or bring it back, as the tracker does (the same endpoints), including what a kill sets off there: a model that replaces
// itself on death offers its replacements, and a killed model gives its own crew a soulstone (the game's auto-soulstone setting).
const soulstones = ref<Record<number, number>>(Object.fromEntries(props.players.map((p) => [p.slot, p.soulstone_pool ?? 0])));
const deathOpen = ref(false);
const deathWarnings = ref<string[]>([]);
const deathReplacements = ref<
    { id: number; display_name: string; front_image: string | null; count: number; health: number | null; selected: boolean }[]
>([]);
const deathKilled = ref<{
    id: string;
    name: string;
    slot: number;
    tokens: { id: number; name: string }[];
    upgrades: unknown[];
    activated: boolean;
    earnsSoulstone: boolean;
    /** Where it stood, for placing its replacements in base contact (CRG p.48). */
    stood: { x: number; y: number; baseMm: number; name: string } | null;
} | null>(null);

// Replaced models still to be set down in base contact with where the original stood, by (new) model id. The board shows the prompt.
const placeAnchors = ref<Record<string, { x: number; y: number; baseMm: number; name: string }>>({});
const forgetAnchor = (id: string) => {
    const rest = { ...placeAnchors.value };
    delete rest[id];
    placeAnchors.value = rest;
};
// ...and kept in this browser per game, so a reload does not forget a model that still has to be set down. Restored once the page is up (not
// during server rendering), and only for models that are still in the game.
const ANCHORS_KEY = `board-anchors-${props.game.uuid}`;
onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(ANCHORS_KEY) ?? '{}') as typeof placeAnchors.value;
        const ids = new Set(props.models.map((m) => String(m.id)));
        placeAnchors.value = { ...Object.fromEntries(Object.entries(saved).filter(([id]) => ids.has(id))), ...placeAnchors.value };
    } catch {
        // nothing saved, or storage is blocked: the prompts just last until the page is left
    }
});
watch(
    placeAnchors,
    (anchors) => {
        try {
            if (Object.keys(anchors).length) localStorage.setItem(ANCHORS_KEY, JSON.stringify(anchors));
            else localStorage.removeItem(ANCHORS_KEY);
        } catch {
            // storage is blocked
        }
    },
    { deep: true },
);
// Upgrades and injuries: the play screen's own pickers, opened from a model's row
const crewUpgrades = ref<InstanceType<typeof GameCrewUpgrades> | null>(null);
/** Someone who plays in this game (not a watcher, not looking back) may attach upgrades to the models they control. */
const canAttach = computed(() => !props.read_only && !viewing.value && props.players.some((p) => p.mine));
const referenceUpgradeIds = computed(() => props.players.flatMap((p) => p.reference_upgrade_ids ?? []));
const crewMembers = computed(() => props.models.map((m) => m.member as any));
const stoodAt = (id: string) => {
    const t = tokens.value.find((x) => x.id === id);
    return t ? { x: t.x, y: t.y, baseMm: t.baseMm, name: t.name } : null;
};
const hasSelectedDeath = computed(() => deathReplacements.value.some((r) => r.selected));

/** Non-peon, non-summoned models with no Summon token give their crew a soulstone when killed. */
async function awardSoulstone(slot: number, name: string) {
    const next = (soulstones.value[slot] ?? 0) + 1;
    soulstones.value = { ...soulstones.value, [slot]: next };
    const res = await gameApi.patch<{ error?: string }>(route('games.play.soulstones', props.game.uuid), { soulstone_pool: next, slot });
    if (res.ok) toast.success(`${name} died: ${playerFor(slot)?.name ?? 'that crew'} gains a soulstone.`);
    else {
        soulstones.value = { ...soulstones.value, [slot]: next - 1 };
        toast.error(res.data.error ?? 'The soulstone was not saved.');
    }
}
async function killChange(id: string, killed: boolean) {
    if (props.read_only) return;
    const model = liveModels.value.find((m) => String(m.id) === id);
    const before = model
        ? {
              id,
              name: model.name,
              slot: model.slot,
              tokens: [...model.tokens],
              upgrades: [...((model.member.attached_upgrades as unknown[]) ?? [])],
              activated: model.activated,
              earnsSoulstone:
                  model.member.station !== 'peon' && !model.member.is_summoned && !model.tokens.some((t) => t.name.toLowerCase() === 'summon'),
              stood: stoodAt(id),
          }
        : null;
    liveModels.value = liveModels.value.map((m) => (String(m.id) === id ? { ...m, killed, health: killed ? 0 : m.max_health } : m));
    const res = await gameApi.post<{ replacements?: typeof deathReplacements.value; error?: string }>(
        route(killed ? 'games.play.crew.kill' : 'games.play.crew.revive', { game: props.game.uuid, gameCrewMember: id }),
    );
    if (!res.ok) {
        toast.error(res.data.error ?? 'That change was not saved.');
        router.reload({ only: ['models'] });
        return;
    }
    if (!killed || !before) return;
    if (res.data.replacements?.length) {
        deathKilled.value = before;
        deathReplacements.value = res.data.replacements.map((r) => ({ ...r, selected: false }));
        deathWarnings.value = [];
        deathOpen.value = true;
    } else if (props.game.auto_soulstone_on_kill !== false && before.earnsSoulstone) {
        await awardSoulstone(before.slot, before.name);
    }
}
/** The player chose replacements: each is added to the crew as the tracker does, inheriting the dead model's tokens, upgrades and activation. */
async function confirmDeath() {
    const killed = deathKilled.value;
    if (!killed) return;
    deathWarnings.value = [];
    let added = false;
    let inherited = false;
    for (const replacement of deathReplacements.value) {
        if (!replacement.selected) continue;
        for (let i = 0; i < replacement.count; i++) {
            const res = await gameApi.post<{ at_limit?: boolean; member_id?: number }>(route('games.play.crew.summon', props.game.uuid), {
                character_id: replacement.id,
                is_replacement: true,
                replacement_health: replacement.health ?? null,
                inherited_tokens: inherited ? [] : killed.tokens,
                inherited_upgrades: inherited ? [] : killed.upgrades,
                is_activated: killed.activated,
                slot: killed.slot,
            });
            if (res.ok) {
                added = true;
                inherited = true;
                if (res.data.member_id && killed.stood) placeAnchors.value = { ...placeAnchors.value, [String(res.data.member_id)]: killed.stood };
            } else if (res.data.at_limit) {
                deathWarnings.value.push(`${replacement.display_name} is at its limit.`);
                replacement.selected = false;
                break;
            }
        }
    }
    if (added) router.reload({ only: ['models'] });
    if (!deathWarnings.value.length) closeDeath();
}
/** No replacement: the model really died, so the crew gets its soulstone as usual. */
function dismissDeath() {
    const killed = deathKilled.value;
    if (killed && props.game.auto_soulstone_on_kill !== false && killed.earnsSoulstone) void awardSoulstone(killed.slot, killed.name);
    closeDeath();
}
function closeDeath() {
    deathOpen.value = false;
    deathKilled.value = null;
    deathReplacements.value = [];
    deathWarnings.value = [];
}

// Replace a model with another (CRG p.48): it keeps its place, tokens, upgrades and health (capped by the new model's maximum).
const replaceTarget = ref<{ id: string; display_name: string; current_health: number; max_health: number } | null>(null);
const replaceOpen = ref(false);
const replaceSearch = ref('');
const replaceResults = ref<{ id: number; name?: string; display_name?: string; front_image: string | null; station?: string | null }[]>([]);
const replaceLoading = ref(false);
let replaceTimer: ReturnType<typeof setTimeout> | null = null;
function openReplace(id: string) {
    const m = crew.value.find((c) => String(c.id) === id);
    if (!m || props.read_only) return;
    replaceTarget.value = { id, display_name: m.display_name, current_health: m.current_health ?? 0, max_health: m.max_health ?? 0 };
    replaceOpen.value = true;
}
function searchReplace(q: string) {
    replaceSearch.value = q;
    if (replaceTimer) clearTimeout(replaceTimer);
    if (q.length < 2) {
        replaceResults.value = [];
        return;
    }
    replaceLoading.value = true;
    replaceTimer = setTimeout(async () => {
        try {
            const res = await fetch(route('api.characters.search') + '?q=' + encodeURIComponent(q));
            if (!res.ok) throw new Error(`status ${res.status}`);
            replaceResults.value = await res.json();
        } catch {
            replaceResults.value = [];
            toast.error('Character search failed', { description: 'Try again in a moment.' });
        }
        replaceLoading.value = false;
    }, 300);
}
function replaceWith(character: { id: number }) {
    const target = replaceTarget.value;
    if (!target) return;
    replaceOpen.value = false;
    replaceSearch.value = '';
    replaceResults.value = [];
    replaceTarget.value = null;
    const stood = stoodAt(target.id);
    router.post(
        route('games.play.crew.replace', { game: props.game.uuid, gameCrewMember: target.id }),
        { character_id: character.id },
        {
            only: ['models'],
            preserveScroll: true,
            preserveState: true,
            // the new model is set down in base contact with where the original stood
            onSuccess: () => {
                if (stood) placeAnchors.value = { ...placeAnchors.value, [target.id]: stood };
            },
        },
    );
}

// The turn bar: pass, and choose initiative (the server keeps the rules)
const turnBusy = ref(false);
async function turnCall(name: 'pass' | 'initiative', body: Record<string, unknown>) {
    if (props.read_only) return;
    turnBusy.value = true;
    const res = await gameApi.post<{ error?: string }>(route(`games.play.turn.${name}`, props.game.uuid), body);
    turnBusy.value = false;
    if (!res.ok) toast.error(res.data.error ?? 'That did not work.');
    router.reload({ only: ['turn', 'models'] });
}
const passTurn = (slot: number) => turnCall('pass', { slot });
const chooseInitiative = (chooser: number, initiative: number) => turnCall('initiative', { slot: chooser, initiative });

// Scheme notes (private to the player): a model or marker picked on the board is saved into the scheme notes the play screen shows.
const schemeNotes = ref<Record<number, SchemeNotes>>(
    Object.fromEntries(props.players.filter((p) => p.mine).map((p) => [p.slot, p.scheme_notes ?? {}])),
);
async function schemeTarget(kind: 'model' | 'marker', slot: number, name: string | null) {
    if (props.read_only || viewing.value) return;
    const current = schemeNotes.value[slot] ?? {};
    const next = { ...current, ...(kind === 'model' ? { selected_model: name } : { selected_marker: name }) };
    schemeNotes.value = { ...schemeNotes.value, [slot]: next };
    const res = await gameApi.patch<{ error?: string }>(route('games.play.scheme-notes', props.game.uuid), { scheme_notes: next, slot });
    if (!res.ok) {
        toast.error(res.data.error ?? 'That was not saved.');
        schemeNotes.value = { ...schemeNotes.value, [slot]: current };
    }
}

const playerFor = (slot: number) => props.players.find((p) => p.slot === slot);
const sideNames = computed(() => ({ a: playerFor(1)?.name ?? 'Player 1', b: playerFor(2)?.name ?? 'Player 2' }));

// The game's Fate deck: the server's, loaded when the section is first opened. A watcher sees the public cards and neither hand.
const fate = useFateGame(props.game.uuid, props.read_only === true);
const fateNames = computed(() => ({ 1: sideNames.value.a, 2: sideNames.value.b }));

/** Models with no saved position line up along their side's table edge (slot 1 on top, slot 2 on the bottom), 1.5" apart at least. */
function defaultSpot(model: BoardModel): { x: number; y: number } {
    // match by id: the models on the table are copies of the props, so identity would not find them
    const row = liveModels.value.filter((m) => m.slot === model.slot);
    const index = row.findIndex((m) => m.id === model.id);
    const step = Math.min(4, (TABLE_SIZE - 2) / Math.max(row.length, 1));
    const start = (TABLE_SIZE - step * (row.length - 1)) / 2;
    return { x: start + step * index, y: model.slot === 1 ? 2 + baseRadius(model.base_mm) : TABLE_SIZE - 2 - baseRadius(model.base_mm) };
}

// Looking back: the table as it stood at the end of a turn (read-only), instead of as it is now.
const viewTurn = ref<number | null>(null);
const historyTurns = computed(() =>
    Object.keys(props.history ?? {})
        .map(Number)
        .sort((a, b) => a - b),
);
const viewing = computed(() => viewTurn.value !== null && !!props.history?.[String(viewTurn.value)]);
const shownPositions = computed(() => (viewing.value ? props.history![String(viewTurn.value)].positions : live.value));
/** The crew as it is, or as it stood at the end of the turn being looked at (health, tokens, killed). */
const modelsShown = computed<BoardModel[]>(() => {
    const snap = viewing.value ? props.history![String(viewTurn.value)].models : undefined;
    if (!snap) return liveModels.value;
    return liveModels.value.map((m) => (snap[String(m.id)] ? { ...m, ...snap[String(m.id)], activated: false } : m));
});
const shownMarkers = computed(() => (viewing.value ? props.history![String(viewTurn.value)].markers : placed.value));
const markerTokens = computed<Token[]>(() =>
    shownMarkers.value.map((pm): Token => {
        const option = props.markers.find((m) => m.id === pm.marker_id);
        return {
            id: pm.id,
            name: option?.name ?? 'Marker',
            side: pm.owner === 2 ? 'b' : 'a',
            baseMm: option?.base_mm ?? 30,
            x: pm.x,
            y: pm.y,
            marker: true,
            markerId: pm.marker_id,
            strategy: option?.strategy || undefined,
            locked: props.read_only || viewing.value || !playerFor(pm.owner)?.mine,
        };
    }),
);

/** The sides this viewer places markers for: their own, or both in a solo game. */
const placeAs = computed(() =>
    props.players.filter((p) => p.mine).map((p) => ({ side: p.slot === 1 ? ('a' as const) : ('b' as const), label: p.name })),
);

const tokens = computed<Token[]>(() => [
    // markers first, so they draw underneath the models and a model on top of one is the one you pick up
    ...markerTokens.value,
    ...modelsShown.value.map((m): Token => {
        const at = shownPositions.value[String(m.id)] ?? defaultSpot(m);
        return {
            id: String(m.id),
            name: m.name,
            side: m.slot === 1 ? 'a' : 'b',
            baseMm: m.base_mm,
            sz: m.sz ?? undefined,
            sp: m.sp ?? undefined,
            x: at.x,
            y: at.y,
            under: 'under' in at ? at.under : undefined,
            ignores: 'ignores' in at ? at.ignores : undefined,
            killed: m.killed,
            peon: m.member.station === 'peon',
            // a summoned model nobody has set on the table yet
            unplaced: !!m.member.is_summoned && !(String(m.id) in live.value),
            info: {
                health: m.health ?? undefined,
                maxHealth: m.max_health ?? undefined,
                activated: m.activated,
                tokens: m.tokens,
                notes: m.notes ?? undefined,
            },
            locked: props.read_only || viewing.value || !playerFor(m.slot)?.mine,
        };
    }),
]);

function terrainChanged(pieces: TerrainPiece[]) {
    liveTerrain.value = pieces;
    router.put(
        route('games.board.terrain', props.game.uuid),
        { terrain: JSON.parse(JSON.stringify(pieces)) },
        { preserveScroll: true, preserveState: true, only: ['terrain'] },
    );
}

/** One request per change: upsert some markers and/or remove some. The server merges, so the other player's edits are kept. */
function saveMarkers(change: { upsert?: { id: string; marker_id: number; owner: number; x: number; y: number }[]; remove?: string[] }) {
    router.put(route('games.board.markers', props.game.uuid), JSON.parse(JSON.stringify(change)), {
        preserveScroll: true,
        preserveState: true,
        only: ['placed_markers'],
    });
}

function addMarker(m: { markerId: number; side: 'a' | 'b'; x: number; y: number }) {
    const owner = m.side === 'a' ? 1 : 2;
    const id = `m${Date.now().toString(36)}${Math.floor(Math.random() * 1296).toString(36)}`;
    placed.value = [...placed.value, { id, marker_id: m.markerId, owner, x: m.x, y: m.y }];
    saveMarkers({ upsert: [{ id, marker_id: m.markerId, owner, x: m.x, y: m.y }] });
}

/** One request per change, like markers: the server merges, so the other player's shared auras are kept. */
function auraChange(change: { upsert?: Aura[]; remove?: string[] }) {
    const gone = new Set([...(change.remove ?? []), ...(change.upsert ?? []).map((a) => a.id)]);
    const upsert = (change.upsert ?? []).map((a) => ({
        id: a.id,
        owner_id: a.ownerId,
        range: a.range,
        locked: a.locked,
        ...(a.locked ? { x: a.x, y: a.y } : {}),
    }));
    sharedAuras.value = [...sharedAuras.value.filter((a) => !gone.has(a.id)), ...(change.upsert ?? []).map((a) => ({ ...a, shared: undefined }))];
    router.put(route('games.board.auras', props.game.uuid), JSON.parse(JSON.stringify({ upsert, remove: change.remove })), {
        preserveScroll: true,
        preserveState: true,
        only: ['shared_auras'],
    });
}

function removeToken(id: string) {
    // Only markers can be picked up; the crews come from the game itself.
    if (!placed.value.some((pm) => pm.id === id)) return;
    placed.value = placed.value.filter((pm) => pm.id !== id);
    // its shared auras go with it (the server drops them too)
    sharedAuras.value = sharedAuras.value.filter((a) => a.ownerId !== id);
    saveMarkers({ remove: [id] });
}

function moved(t: Token) {
    if (t.marker && t.markerId !== undefined) {
        placed.value = placed.value.map((pm) => (pm.id === t.id ? { ...pm, x: t.x, y: t.y } : pm));
        saveMarkers({ upsert: [{ id: t.id, marker_id: t.markerId, owner: t.side === 'b' ? 2 : 1, x: t.x, y: t.y }] });
        return;
    }
    router.put(
        route('games.board.update', props.game.uuid),
        { positions: { [t.id]: { x: t.x, y: t.y, ...(t.under ? { under: true } : {}), ...(t.ignores?.length ? { ignores: t.ignores } : {}) } } },
        { preserveScroll: true, preserveState: true, only: ['positions'] },
    );
}
</script>

<template>
    <Head title="Game board (preview)" />
    <PageBanner title="Game board">
        <template #subtitle>
            <div v-if="read_only" class="line-clamp-1 px-2 text-sm text-muted-foreground sm:line-clamp-none" data-testid="watch-note">
                You are watching. The board refreshes by itself every few seconds and cannot be changed from here.
            </div>
            <div v-else class="line-clamp-1 px-2 text-sm text-muted-foreground sm:line-clamp-none">
                Your crew is on the table. Drag your own models; the other crew is locked. Either player can lay out the terrain. Everything is saved
                to the game.
            </div>
        </template>
        <template #actions>
            <Button as-child variant="outline" size="sm" class="max-sm:min-h-11">
                <Link :href="read_only ? route('games.observe', game.uuid) : route('games.show', game.uuid)"
                    ><ArrowLeft class="mr-1 size-4" />Back to the game</Link
                >
            </Button>
        </template>
    </PageBanner>
    <div class="container mx-auto mt-2 px-2 max-lg:pb-24 sm:mt-4 sm:px-4 lg:px-6">
        <div v-if="clock || turn || historyTurns.length" class="mb-3 space-y-2">
            <GameRoundClock v-if="clock" :clock="clock" />
            <GameTurnBar
                v-if="turn && !viewing"
                :turn="turn"
                :names="Object.fromEntries(players.map((p) => [p.slot, p.name]))"
                :mine="players.filter((p) => p.mine).map((p) => p.slot)"
                :busy="turnBusy"
                @pass="passTurn"
                @initiative="chooseInitiative"
            />
            <!-- a look back at where everything stood at the end of each turn -->
            <div v-if="historyTurns.length" class="flex flex-wrap items-center gap-2 text-sm" data-testid="history-bar">
                <label class="flex items-center gap-2">
                    <span class="text-muted-foreground">Look back</span>
                    <select
                        class="rounded-md border bg-background px-2 py-1.5 max-sm:min-h-11"
                        data-testid="history-select"
                        :value="viewTurn ?? ''"
                        @change="viewTurn = ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null"
                    >
                        <option value="">Now</option>
                        <option v-for="n in historyTurns" :key="n" :value="n">End of turn {{ n }}</option>
                    </select>
                </label>
                <span v-if="viewing" class="text-xs text-muted-foreground" data-testid="history-note">
                    The table at the end of turn {{ viewTurn }}. Nothing can be changed while you look back.
                </span>
            </div>
        </div>
        <TabletopBoard
            :can-attach="canAttach"
            :is-campaign="!!game.is_campaign"
            @open-upgrades="(id: string) => crewUpgrades?.openUpgrades(Number(id))"
            @open-injury="(id: string) => crewUpgrades?.openInjury(Number(id))"
            :place-anchors="placeAnchors"
            @placed="forgetAnchor"
            :tokens="tokens"
            :terrain="liveTerrain"
            :markers="markers"
            :place-as="placeAs"
            :shared-auras="sharedAuras"
            :read-only="read_only || viewing"
            :token-options="token_options"
            :crew="crew"
            :fate="fate"
            @model-change="modelChange"
            @replace-model="openReplace"
            @kill-change="killChange"
            :scheme-notes="schemeNotes"
            @scheme-target="schemeTarget"
            @aura-change="auraChange"
            :deployment-variant="variant"
            @variant-changed="variantChanged"
            :table-images="table_images"
            :layouts="layouts ?? []"
            :layouts-loading="layouts === undefined"
            :layout-target="{ kind: 'game', uuid: game.uuid }"
            :signed-in="!!page.props.auth.user"
            :table-image="tableImage"
            :terrain-images="terrain_images"
            @table-image-changed="tableImageChanged"
            @add-marker="addMarker"
            @remove="removeToken"
            @terrain-changed="terrainChanged"
            :storage-key="game.uuid"
            :deployment="game.deployment ?? undefined"
            :side-names="sideNames"
            @moved="moved"
        >
            <template #fate="{ terrain }">
                <FateDock :feed="fate" :names="fateNames" :read-only="read_only" :collapsed="terrain" />
            </template>
        </TabletopBoard>
    </div>
    <GameCrewUpgrades
        v-if="canAttach"
        ref="crewUpgrades"
        :game-uuid="game.uuid"
        :members="crewMembers"
        :upgrades="character_upgrades ?? []"
        :injuries="character_injuries ?? []"
        :reference-ids="referenceUpgradeIds"
        @attached="() => router.reload({ only: ['models'] })"
    />
    <GameReplaceOnDeathDialog
        v-if="!read_only"
        :open="deathOpen"
        :replacements="deathReplacements"
        :warnings="deathWarnings"
        :has-selected="hasSelectedDeath"
        @update:open="deathOpen = $event"
        @toggle="
            (rid: number) => {
                const r = deathReplacements.find((x) => x.id === rid);
                if (r) r.selected = !r.selected;
            }
        "
        @confirm="confirmDeath"
        @dismiss="dismissDeath"
    />
    <GameReplaceDialog
        v-if="!read_only"
        :open="replaceOpen"
        :member="replaceTarget"
        :reference-characters="[]"
        :results="replaceResults"
        :search="replaceSearch"
        :loading="replaceLoading"
        @update:open="replaceOpen = $event"
        @update:search="searchReplace"
        @select="replaceWith"
    />
</template>
