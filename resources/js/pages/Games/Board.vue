<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import TabletopBoard from '@/components/Tabletop/TabletopBoard.vue';
import { Button } from '@/components/ui/button';
import { TABLE_SIZE, baseRadius, type Token } from '@/lib/tabletop/geometry';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { loadEcho } from '@/lib/echo';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface BoardModel {
    id: number;
    name: string;
    slot: number;
    base_mm: number;
    killed: boolean;
}

const props = defineProps<{
    game: { uuid: string; is_solo: boolean };
    players: { slot: number; name: string; mine: boolean }[];
    models: BoardModel[];
    positions: Record<string, { x: number; y: number }>;
}>();

// Saved positions, plus moves the other player has just made (applied straight from the broadcast).
const live = ref<Record<string, { x: number; y: number }>>({ ...props.positions });
watch(
    () => props.positions,
    (next) => (live.value = { ...next }),
);

let joined = false;
const resync = () => document.visibilityState === 'visible' && router.reload({ only: ['positions'] });

onMounted(async () => {
    document.addEventListener('visibilitychange', resync);
    const echo = await loadEcho();
    if (!echo) return;
    joined = true;
    echo.join(`game.${props.game.uuid}`).listen('.GameBoardMoved', (e: { positions: Record<string, { x: number; y: number }> }) => {
        live.value = { ...live.value, ...e.positions };
    });
});
onUnmounted(() => {
    document.removeEventListener('visibilitychange', resync);
    if (joined) window.Echo?.leave(`game.${props.game.uuid}`);
});

const playerFor = (slot: number) => props.players.find((p) => p.slot === slot);
const sideNames = computed(() => ({ a: playerFor(1)?.name ?? 'Player 1', b: playerFor(2)?.name ?? 'Player 2' }));

/** Models with no saved position line up along their side's table edge (slot 1 on top, slot 2 on the bottom), 1.5" apart at least. */
function defaultSpot(model: BoardModel): { x: number; y: number } {
    const row = props.models.filter((m) => m.slot === model.slot);
    const index = row.indexOf(model);
    const step = Math.min(4, (TABLE_SIZE - 2) / Math.max(row.length, 1));
    const start = (TABLE_SIZE - step * (row.length - 1)) / 2;
    return { x: start + step * index, y: model.slot === 1 ? 2 + baseRadius(model.base_mm) : TABLE_SIZE - 2 - baseRadius(model.base_mm) };
}

const tokens = computed<Token[]>(() =>
    props.models.map((m) => {
        const at = live.value[String(m.id)] ?? defaultSpot(m);
        return {
            id: String(m.id),
            name: m.name,
            side: m.slot === 1 ? 'a' : 'b',
            baseMm: m.base_mm,
            x: at.x,
            y: at.y,
            killed: m.killed,
            locked: !playerFor(m.slot)?.mine,
        };
    }),
);

function moved(t: Token) {
    router.put(
        route('games.board.update', props.game.uuid),
        { positions: { [t.id]: { x: t.x, y: t.y } } },
        { preserveScroll: true, preserveState: true, only: ['positions'] },
    );
}
</script>

<template>
    <Head title="Game board (preview)" />
    <PageBanner title="Game board">
        <template #subtitle>
            <div class="px-2 text-sm text-muted-foreground">
                Your crew is on the table. Drag your own models; the other crew is locked. Positions are saved to the game.
            </div>
        </template>
        <template #actions>
            <Button as-child variant="outline" size="sm">
                <Link :href="route('games.show', game.uuid)"><ArrowLeft class="mr-1 size-4" />Back to the game</Link>
            </Button>
        </template>
    </PageBanner>
    <div class="container mx-auto mt-4 px-2 sm:px-4 lg:px-6">
        <TabletopBoard :tokens="tokens" :storage-key="game.uuid" :side-names="sideNames" @moved="moved" />
    </div>
</template>
