<script setup lang="ts">
/**
 * The deployment step of a tabletop game, on the board: the defender divides their crew, the attacker chooses which group goes first, then
 * the groups are placed in the rulebook's order. It only says what is going on and sends what the player decides; the board does the
 * placing, and the server (App\Support\Game\GameDeployment) keeps the order. Its height must not change while a model is dragged, so
 * the "outside the zone" line is one fixed line.
 */
import { Button } from '@/components/ui/button';
import { DEPLOYMENT_TYPES, deployWords, type DeployPlayer, type DeployState } from '@/lib/tabletop/deployment';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    state: DeployState;
    players: DeployPlayer[];
    /** Every crew member on the table: id, name and the slot that owns it. */
    models: { id: number; name: string; slot: number }[];
    /** The models of this phase that are not completely inside their zone yet. */
    outside: number[];
    busy: boolean;
    gameUuid: string;
}>();

const emit = defineEmits<{
    (e: 'split', later: number[]): void;
    (e: 'choose', first: 'now' | 'later'): void;
    (e: 'done'): void;
    (e: 'skip'): void;
    (e: 'rotate'): void;
    (e: 'type', deployment: string): void;
}>();

const words = computed(() => deployWords(props.state, props.players));
const myRole = computed(() => {
    const slot = props.players.find((p) => p.mine)?.slot;
    return slot === props.state.attacker_slot ? 'attacker' : slot === props.state.defender_slot ? 'defender' : null;
});
const iAttack = computed(() => props.players.some((p) => p.mine && p.slot === props.state.attacker_slot));
const iSpeakFor = computed(() => props.players.filter((p) => p.mine).map((p) => p.slot));
const iSkipped = computed(() => iSpeakFor.value.length > 0 && iSpeakFor.value.every((s) => props.state.skipped.includes(s)));

/** Either player may pick the kind of deployment until a crew is being placed: the zones are drawn on the table as they choose. */
const canPickType = computed(() => (props.state.phase === 'split' || props.state.phase === 'choose') && props.players.some((p) => p.mine));

const nameOf = (id: number) => props.models.find((m) => m.id === id)?.name ?? 'Model';
const defenderModels = computed(() => props.models.filter((m) => m.slot === props.state.defender_slot));

// the defender's choice of models to deploy second
const later = ref<number[]>([...props.state.later]);
watch(
    () => props.state.later,
    (next) => (later.value = [...next]),
);
const toggle = (id: number) => (later.value = later.value.includes(id) ? later.value.filter((x) => x !== id) : [...later.value, id]);

// the two groups the attacker chooses between
const laterGroup = computed(() => props.state.later);
const nowGroup = computed(() => defenderModels.value.map((m) => m.id).filter((id) => !props.state.later.includes(id)));
const names = (ids: number[]) => ids.map(nameOf).join(', ');

const placingPhase = computed(() => ['first', 'attacker', 'second'].includes(props.state.phase));
const outsideNames = computed(() => names(props.outside));
</script>

<template>
    <section class="mb-3 rounded-lg border bg-card p-3 text-sm" aria-label="Deployment" data-testid="deploy-panel" :data-phase="state.phase">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    Deployment<span v-if="myRole"> · you are the {{ myRole }}</span>
                </p>
                <!-- the phase changes are announced -->
                <p class="mt-0.5 font-medium" role="status" aria-live="polite" data-testid="deploy-headline">{{ words.headline }}</p>
            </div>
            <Button
                v-if="iAttack && (state.phase === 'split' || state.phase === 'choose')"
                variant="outline"
                size="sm"
                class="max-sm:min-h-11"
                data-testid="deploy-rotate"
                @click="emit('rotate')"
            >
                Turn the zones
            </Button>
        </div>

        <!-- which deployment: seen on the table as it is chosen -->
        <div v-if="canPickType" class="mt-3 flex flex-wrap items-center gap-1.5" role="group" aria-label="Deployment type" data-testid="deploy-type">
            <span class="mr-1 text-xs text-muted-foreground">Deployment</span>
            <Button
                v-for="t in DEPLOYMENT_TYPES"
                :key="t.value"
                size="sm"
                class="max-sm:min-h-11"
                :variant="state.deployment === t.value ? 'default' : 'outline'"
                :aria-pressed="state.deployment === t.value"
                :disabled="busy"
                :data-testid="`deploy-type-${t.value}`"
                @click="state.deployment !== t.value && emit('type', t.value)"
            >
                {{ t.label }}
            </Button>
        </div>

        <!-- the defender divides their crew -->
        <div v-if="state.phase === 'split' && words.yourTurn" class="mt-3" data-testid="deploy-split">
            <p class="mb-1.5 text-xs text-muted-foreground">Tick the models you will deploy second. Tick none to keep the crew together.</p>
            <ul class="grid gap-1 sm:grid-cols-2">
                <li v-for="m in defenderModels" :key="m.id">
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-2 sm:min-h-9">
                        <input
                            type="checkbox"
                            class="size-4"
                            :checked="later.includes(m.id)"
                            :data-testid="`deploy-later-${m.id}`"
                            @change="toggle(m.id)"
                        />
                        <span class="truncate">{{ m.name }}</span>
                    </label>
                </li>
            </ul>
            <Button class="mt-2 max-sm:min-h-11" :disabled="busy" data-testid="deploy-split-confirm" @click="emit('split', later)">
                {{ later.length === 0 ? 'Keep the crew together' : `Hold back ${later.length} for later` }}
            </Button>
        </div>

        <!-- the attacker chooses which group goes first -->
        <div v-else-if="state.phase === 'choose'" class="mt-3 grid gap-2 sm:grid-cols-2" data-testid="deploy-choose">
            <div
                v-for="group in [
                    { key: 'now', ids: nowGroup },
                    { key: 'later', ids: laterGroup },
                ]"
                :key="group.key"
                class="rounded-md border p-2"
            >
                <p class="truncate text-xs text-muted-foreground">{{ names(group.ids) }}</p>
                <Button
                    v-if="words.yourTurn"
                    size="sm"
                    class="mt-1.5 max-sm:min-h-11"
                    :disabled="busy"
                    :data-testid="`deploy-choose-${group.key}`"
                    @click="emit('choose', group.key as 'now' | 'later')"
                >
                    Deploy this group first
                </Button>
            </div>
        </div>

        <!-- a group is being placed -->
        <div v-else-if="placingPhase" class="mt-3" data-testid="deploy-placing">
            <p class="truncate text-xs text-muted-foreground">Placing: {{ names(state.placing) }}</p>
            <template v-if="words.yourTurn">
                <!-- one fixed line, so the board does not move while a model is dragged -->
                <p
                    class="mt-1 h-5 truncate text-xs"
                    :class="outside.length ? 'text-red-600 dark:text-red-400' : 'text-green-700 dark:text-green-400'"
                    data-testid="deploy-zone-note"
                >
                    {{ outside.length ? `Not completely inside your zone: ${outsideNames}` : 'Every model is completely inside your zone.' }}
                </p>
                <Button class="mt-1 max-sm:min-h-11" :disabled="busy || outside.length > 0" data-testid="deploy-done" @click="emit('done')">
                    Finished placing
                </Button>
            </template>
        </div>

        <div v-else-if="state.phase === 'done'" class="mt-3" data-testid="deploy-over">
            <Button as-child class="max-sm:min-h-11">
                <Link :href="route('games.show', gameUuid)">Choose your scheme</Link>
            </Button>
        </div>

        <div v-if="state.phase !== 'done'" class="mt-3 flex flex-wrap items-center gap-2 border-t pt-2">
            <Button variant="ghost" size="sm" class="max-sm:min-h-11" :disabled="busy || iSkipped" data-testid="deploy-skip" @click="emit('skip')">
                Start without deploying
            </Button>
            <span v-if="iSkipped" class="text-xs text-muted-foreground">Waiting for the other player to agree.</span>
            <span v-else class="text-xs text-muted-foreground">Use this if the crews are already set up elsewhere. Both players need to agree.</span>
        </div>
    </section>
</template>
