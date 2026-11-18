<script setup lang="ts">
import FateDock from '@/components/Fate/FateDock.vue';
import GameReplaceDialog from '@/components/Game/GameReplaceDialog.vue';
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
    game: { uuid: string; is_solo: boolean; deployment: DeploymentKind | null };
    /** Someone watching an observable game: nothing can be changed, and the page refreshes itself. */
    read_only?: boolean;
    players: { slot: number; name: string; mine: boolean }[];
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
const resync = () =>
    document.visibilityState === 'visible' &&
    router.reload({ only: ['positions', 'terrain', 'placed_markers', 'shared_auras', 'deployment_variant', 'table_image', 'models'] });

let poll: ReturnType<typeof setInterval> | null = null;
onMounted(async () => {
    document.addEventListener('visibilitychange', resync);
    // Load the Fate deck a moment after the table is up (not before it), so a flip made for a model shows beside it without opening the deck.
    // A watcher polls already, so only the players do this.
    if (!props.read_only) window.setTimeout(() => void fate.start(), 1200);
    if (props.read_only) {
        // Observers are not on the players' live channel, so look again every few seconds while the tab is showing.
        poll = setInterval(resync, 8000);
        return;
    }
    const echo = await loadEcho();
    if (!echo) return;
    joined = true;
    echo.join(`game.${props.game.uuid}`)
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
            // the play screen changed a model (health, tokens, a kill): look again, once if several arrive together
            if (modelsTimer) clearTimeout(modelsTimer);
            modelsTimer = setTimeout(() => router.reload({ only: ['models'] }), 400);
        })
        .listen('.GameBoardSetupChanged', (e: { deployment_variant: number; table_image: string | null }) => {
            variant.value = e.deployment_variant;
            tableImage.value = e.table_image;
        })
        .listen('.GameBoardAurasChanged', (e: { auras: SharedAura[] }) => {
            sharedAuras.value = e.auras.map(toAura);
        });
});
onUnmounted(() => {
    document.removeEventListener('visibilitychange', resync);
    if (poll) clearInterval(poll);
    if (modelsTimer) clearTimeout(modelsTimer);
    if (joined) window.Echo?.leave(`game.${props.game.uuid}`);
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
    liveModels.value.map((m) => ({
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
    router.post(
        route('games.play.crew.replace', { game: props.game.uuid, gameCrewMember: target.id }),
        { character_id: character.id },
        { only: ['models'], preserveScroll: true, preserveState: true },
    );
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

const markerTokens = computed<Token[]>(() =>
    placed.value.map((pm): Token => {
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
            locked: props.read_only || !playerFor(pm.owner)?.mine,
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
    ...liveModels.value.map((m): Token => {
        const at = live.value[String(m.id)] ?? defaultSpot(m);
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
            info: {
                health: m.health ?? undefined,
                maxHealth: m.max_health ?? undefined,
                activated: m.activated,
                tokens: m.tokens,
                notes: m.notes ?? undefined,
            },
            locked: props.read_only || !playerFor(m.slot)?.mine,
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
            <div v-if="read_only" class="px-2 text-sm text-muted-foreground" data-testid="watch-note">
                You are watching. The board refreshes by itself every few seconds and cannot be changed from here.
            </div>
            <div v-else class="px-2 text-sm text-muted-foreground">
                Your crew is on the table. Drag your own models; the other crew is locked. Either player can lay out the terrain. Everything is saved
                to the game.
            </div>
        </template>
        <template #actions>
            <Button as-child variant="outline" size="sm">
                <Link :href="read_only ? route('games.observe', game.uuid) : route('games.show', game.uuid)"
                    ><ArrowLeft class="mr-1 size-4" />Back to the game</Link
                >
            </Button>
        </template>
    </PageBanner>
    <div class="container mx-auto mt-4 px-2 sm:px-4 lg:px-6">
        <TabletopBoard
            :tokens="tokens"
            :terrain="liveTerrain"
            :markers="markers"
            :place-as="placeAs"
            :shared-auras="sharedAuras"
            :read-only="read_only"
            :token-options="token_options"
            :crew="crew"
            :fate="fate"
            @model-change="modelChange"
            @replace-model="openReplace"
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
            <template #fate>
                <FateDock :feed="fate" :names="fateNames" :read-only="read_only" />
            </template>
        </TabletopBoard>
    </div>
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
