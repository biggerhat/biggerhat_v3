<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import { Link } from '@inertiajs/vue3';
import { Medal } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** Put the event into one of the organizer's leagues, so its finishing places count toward that league's leaderboard. */
const props = defineProps<{
    tournament: { uuid: string; team_size?: number };
    series: { uuid: string; name: string } | null;
    mySeries: { uuid: string; name: string }[];
}>();
const emit = defineEmits<{ (e: 'saved'): void }>();

const NONE = '__none';
const toast = useToast();
const { doModalAction } = useTournament(computed(() => props.tournament as never));
const selected = ref(props.series?.uuid ?? NONE);
const error = ref<string | null>(null);
const isTeamEvent = computed(() => (props.tournament.team_size ?? 1) > 1);

watch(
    () => props.series?.uuid,
    (uuid) => (selected.value = uuid ?? NONE),
);

const change = async (value: unknown) => {
    const next = String(value);
    if (next === (props.series?.uuid ?? NONE)) return;
    error.value = null;
    const { ok, error: message } = await doModalAction(route('tournaments.series.assign', props.tournament.uuid), 'PUT', {
        series: next === NONE ? null : next,
    });
    if (!ok) {
        error.value = message;
        selected.value = props.series?.uuid ?? NONE;
        return;
    }
    toast.success(next === NONE ? 'Removed from the league' : 'Added to the league');
    emit('saved');
};
</script>

<template>
    <Card class="mb-4" data-testid="series-card">
        <CardContent class="space-y-3 p-4 sm:p-5">
            <div>
                <h2 class="flex items-center gap-1.5 text-sm font-semibold"><Medal class="size-4" aria-hidden="true" /> League</h2>
                <p class="text-xs text-muted-foreground">
                    Count this event toward a league: its finishing places become points on the league's leaderboard once the event is finished.
                </p>
            </div>
            <p v-if="isTeamEvent" class="text-xs text-muted-foreground">Team events can't be part of a league — a league ranks individual players.</p>
            <template v-else>
                <Select :model-value="selected" @update:model-value="change">
                    <SelectTrigger class="h-9 max-w-sm text-sm" aria-label="League"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">Not in a league</SelectItem>
                        <SelectItem v-for="s in mySeries" :key="s.uuid" :value="s.uuid">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="!mySeries.length" class="text-xs text-muted-foreground">
                    You have no leagues yet. <Link :href="route('tournaments.series.create')" class="underline">Start one</Link>.
                </p>
                <p v-if="series" class="text-xs">
                    <Link :href="route('tournaments.series.show', series.uuid)" class="underline">View {{ series.name }}</Link>
                </p>
                <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
            </template>
        </CardContent>
    </Card>
</template>
