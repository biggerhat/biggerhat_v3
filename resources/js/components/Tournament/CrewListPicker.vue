<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router, usePage } from '@inertiajs/vue3';
import { ClipboardList, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** A registered player attaches one of their saved Crew Builder lists for the organizers; it locks when the event starts. */
interface CrewListProps {
    required: boolean;
    open: boolean;
    selected_id: number | null;
    declared_faction: string | null;
    builds: { id: number; name: string; faction: string; faction_label: string; master: string | null; encounter_size: number }[];
}

const props = defineProps<{ tournamentUuid: string; eventSize: number; crewLists: CrewListProps }>();

const choice = ref<string>(props.crewLists.selected_id ? String(props.crewLists.selected_id) : '');
watch(
    () => props.crewLists.selected_id,
    (id) => (choice.value = id ? String(id) : ''),
);

const selected = computed(() => props.crewLists.builds.find((b) => b.id === props.crewLists.selected_id) ?? null);
// Only offer crews that fit the faction the player registered with.
const options = computed(() =>
    props.crewLists.builds.filter((b) => !props.crewLists.declared_faction || b.faction === props.crewLists.declared_faction),
);
const changed = computed(() => choice.value !== (props.crewLists.selected_id ? String(props.crewLists.selected_id) : ''));
const error = computed(() => (usePage().props.errors as Record<string, string> | undefined)?.crew ?? null);

const busy = ref(false);
const send = (id: number | null) =>
    router.put(
        route('tournaments.crew_list', props.tournamentUuid),
        { crew_build_id: id },
        { preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );
const label = (b: CrewListProps['builds'][number]) => `${b.name}${b.master ? ` — ${b.master}` : ''} (${b.faction_label}, ${b.encounter_size}ss)`;
</script>

<template>
    <Card class="mb-6" data-no-print>
        <CardContent class="space-y-3 p-4">
            <div class="flex items-center gap-2 font-semibold"><ClipboardList class="size-4" /> Your crew list</div>

            <p v-if="selected" class="text-sm">
                Submitted: <span class="font-medium">{{ selected.name }}</span>
                <span v-if="selected.master" class="text-muted-foreground"> — {{ selected.master }}</span>
                <span v-if="selected.encounter_size !== eventSize" class="text-amber-700 dark:text-amber-400">
                    (built for {{ selected.encounter_size }}ss; this event is {{ eventSize }}ss)
                </span>
            </p>
            <p v-else class="text-sm text-muted-foreground">
                {{
                    crewLists.required
                        ? 'The organizer asks for your crew before the event starts.'
                        : 'Optional — the organizer can see it if you share one.'
                }}
            </p>

            <template v-if="crewLists.open">
                <div v-if="options.length" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <Select v-model="choice">
                        <SelectTrigger class="h-9 w-full text-sm sm:max-w-md"><SelectValue placeholder="Choose one of your crews…" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="b in options" :key="b.id" :value="String(b.id)">{{ label(b) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="flex gap-2">
                        <Button size="sm" :disabled="busy || !choice || !changed" @click="send(Number(choice))">
                            <Loader2 v-if="busy" class="mr-1 size-3 animate-spin" /> {{ selected ? 'Update' : 'Submit' }}
                        </Button>
                        <Button v-if="selected" size="sm" variant="ghost" :disabled="busy" @click="send(null)">Remove</Button>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    You have no saved crews{{ crewLists.declared_faction ? ' for the faction you registered with' : '' }} yet.
                    <a :href="route('tools.crew_builder.editor')" target="_blank" rel="noopener" class="text-primary hover:underline"
                        >Build one in the Crew Builder</a
                    >, then come back.
                </p>
            </template>
            <p v-else class="text-xs text-muted-foreground">
                Lists are locked now that the tournament has started. Ask the organizer if something needs to change.
            </p>

            <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
        </CardContent>
    </Card>
</template>
