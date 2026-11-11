<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import { roundName, type CutState, type TournamentRound } from '@/types/tournament';
import { Loader2, Swords } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Single-elimination top cut: start it once the Swiss rounds are done (seeded from the standings), then advance the
 * winners after each cut round. The cut rounds are played and scored like any other round.
 */
const props = defineProps<{ tournament: { uuid: string; status: string }; cut: CutState; latestRound: TournamentRound | null }>();
const emit = defineEmits<{ (e: 'changed'): void }>();

const confirm = useConfirm();
const toast = useToast();
const { doModalAction } = useTournament(computed(() => props.tournament as never));

const size = ref<string>(String(defaultSize()));
const busy = ref(false);
const error = ref<string | null>(null);

function defaultSize(): number {
    const sizes = props.cut.available_sizes;
    return sizes.filter((s) => s <= 8).pop() ?? sizes[0] ?? 4;
}
watch(
    () => props.cut.available_sizes,
    () => {
        if (!props.cut.available_sizes.includes(Number(size.value))) size.value = String(defaultSize());
    },
);

const sizeLabel = (n: number) => (n === 2 ? 'Final only (top 2)' : n === 4 ? 'Top 4 (semifinals)' : n === 8 ? 'Top 8 (quarterfinals)' : `Top ${n}`);

// After a finished cut round that was not the final, the winners are ready to be paired onward.
const gamesIn = (round: TournamentRound | null) => (round?.games ?? []).filter((g) => !g.is_bye).length;
const canAdvance = computed(
    () =>
        props.tournament.status === 'active' &&
        !!props.latestRound?.is_cut &&
        props.latestRound.status === 'completed' &&
        gamesIn(props.latestRound) > 1,
);
const canStart = computed(() => props.tournament.status === 'active' && !props.cut.started && props.cut.available_sizes.length > 0);

const run = async (url: string, body: Record<string, unknown> | undefined, done: string) => {
    busy.value = true;
    error.value = null;
    const { ok, error: message } = await doModalAction(url, 'POST', body);
    busy.value = false;
    if (!ok) {
        error.value = message;
        return;
    }
    toast.success(done);
    emit('changed');
};

const start = async () => {
    const n = Number(size.value);
    if (
        !(await confirm({
            title: `Start a top ${n}?`,
            message: `The best ${n} players from the Swiss standings are seeded into a single-elimination bracket and the first round is paired. Swiss standings stay as they are.`,
            confirmLabel: 'Start top cut',
        }))
    )
        return;
    await run(route('tournaments.cut.start', props.tournament.uuid), { size: n }, 'Top cut paired');
};

const advance = () => run(route('tournaments.cut.advance', props.tournament.uuid), undefined, 'Bracket advanced');
defineExpose({ advance, canAdvance, canStart, busy });
</script>

<template>
    <Card v-if="canStart || canAdvance" class="mb-4" data-testid="top-cut">
        <CardContent class="space-y-3 p-4 sm:p-5">
            <div class="flex items-start gap-3">
                <Swords class="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold">
                        {{ canAdvance && latestRound ? `${roundName(latestRound)} are done` : 'Top cut' }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="canAdvance">Pair the winners into the next round of the bracket.</template>
                        <template v-else>
                            The Swiss rounds are finished. Play a single-elimination cut between the best players — or just finalize the standings.
                        </template>
                    </p>
                </div>
            </div>
            <div v-if="canStart" class="flex flex-wrap items-center gap-2">
                <Select v-model="size">
                    <SelectTrigger class="h-9 w-56 text-sm" aria-label="Cut size"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="n in cut.available_sizes" :key="n" :value="String(n)">{{ sizeLabel(n) }}</SelectItem>
                    </SelectContent>
                </Select>
                <Button size="sm" :disabled="busy" @click="start"><Loader2 v-if="busy" class="mr-1 size-3.5 animate-spin" /> Start top cut</Button>
            </div>
            <Button v-else size="sm" :disabled="busy" @click="advance"
                ><Loader2 v-if="busy" class="mr-1 size-3.5 animate-spin" /> Advance bracket</Button
            >
            <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
        </CardContent>
    </Card>
</template>
