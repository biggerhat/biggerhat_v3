<script setup lang="ts">
import AwardRow from '@/components/Tournament/AwardRow.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import type { TournamentAward } from '@/types/tournament';
import { computed, ref } from 'vue';

/** The organizer's side awards (best painted, sportsmanship, ...): name them, give them to a player, tell the winner. */
const props = defineProps<{
    tournament: { uuid: string };
    awards: TournamentAward[];
    players: { id: number; display_name: string; is_ringer?: boolean }[];
}>();
const emit = defineEmits<{ (e: 'saved'): void }>();

const toast = useToast();
const confirm = useConfirm();
const { doModalAction } = useTournament(computed(() => props.tournament as never));

const PRESETS = ['Best Painted', 'Best Sportsmanship', 'Best Crew Theme', 'Best Comeback'];
const choices = computed(() => props.players.filter((p) => !p.is_ringer));
const busy = ref<number | 'new' | null>(null);
const error = ref<{ key: number | 'new'; message: string } | null>(null);
const newKey = ref(0);
const preset = ref<string | null>(null);

const errorFor = (key: number | 'new') => (error.value?.key === key ? error.value.message : null);

const run = async (key: number | 'new', url: string, method: string, body: Record<string, unknown> | undefined, done: string) => {
    busy.value = key;
    error.value = null;
    const { ok, error: message } = await doModalAction(url, method, body);
    busy.value = null;
    if (!ok) {
        error.value = { key, message: message ?? 'Could not save the award.' };
        return;
    }
    toast.success(done);
    emit('saved');
};

const create = async (value: { name: string; player_id: number | null; note: string | null }) => {
    await run('new', route('tournaments.awards.store', props.tournament.uuid), 'POST', value, 'Award added');
    if (!error.value) newKey.value++;
};
const update = (award: TournamentAward, value: { name: string; player_id: number | null; note: string | null }) =>
    run(award.id, route('tournaments.awards.update', [props.tournament.uuid, award.id]), 'PUT', value, 'Award saved');
const remove = async (award: TournamentAward) => {
    if (
        !(await confirm({
            title: `Delete "${award.name}"?`,
            message: 'The award is removed from the event page.',
            confirmLabel: 'Delete',
            destructive: true,
        }))
    )
        return;
    await run(award.id, route('tournaments.awards.destroy', [props.tournament.uuid, award.id]), 'DELETE', undefined, 'Award deleted');
};
const usePreset = (name: string) => {
    preset.value = name;
    newKey.value++;
};
</script>

<template>
    <Card data-testid="awards-editor">
        <CardContent class="space-y-3 p-4 sm:p-5">
            <div>
                <h2 class="text-sm font-semibold">Side awards</h2>
                <p class="text-xs text-muted-foreground">
                    Best painted, sportsmanship, anything you like. Winners are shown on the event page and results sheet and get a notification.
                </p>
            </div>
            <AwardRow
                v-for="a in awards"
                :key="`${a.id}-${a.name}-${a.player_id}-${a.note}`"
                :award="a"
                :players="choices"
                :saving="busy === a.id"
                :error="errorFor(a.id)"
                @save="update(a, $event)"
                @delete="remove(a)"
            />
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-muted-foreground">Ideas:</span>
                <button
                    v-for="p in PRESETS"
                    :key="p"
                    type="button"
                    class="rounded-full border px-2 py-0.5 text-xs hover:bg-accent"
                    @click="usePreset(p)"
                >
                    {{ p }}
                </button>
                <Badge v-if="awards.length" variant="secondary" class="ml-auto px-1.5 py-0 text-2xs">{{ awards.length }}/12</Badge>
            </div>
            <AwardRow
                :key="`new-${newKey}`"
                :award="null"
                :initial-name="preset ?? undefined"
                :players="choices"
                :saving="busy === 'new'"
                :error="errorFor('new')"
                @save="create"
            />
        </CardContent>
    </Card>
</template>
