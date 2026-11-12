<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import { ClipboardCheck, Loader2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** Scorekeepers: accounts that may enter and correct results from the scoring desk but cannot change anything else. */
const props = defineProps<{ tournament: { uuid: string; scorers?: { id: number; name: string }[]; organizers: { id: number }[] } }>();
const emit = defineEmits<{ (e: 'saved'): void }>();

const toast = useToast();
const confirm = useConfirm();
const { doModalAction } = useTournament(computed(() => props.tournament as never));

const scorers = computed(() => props.tournament.scorers ?? []);
const query = ref('');
const results = ref<{ id: number; name: string }[]>([]);
const searching = ref(false);
const error = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;

watch(query, (q) => {
    if (timer) clearTimeout(timer);
    if (q.trim().length < 2) {
        results.value = [];
        return;
    }
    timer = setTimeout(async () => {
        searching.value = true;
        try {
            const res = await fetch(`${route('tournaments.users.search', props.tournament.uuid)}?q=${encodeURIComponent(q.trim())}`, {
                headers: { Accept: 'application/json' },
            });
            const taken = new Set([...scorers.value.map((s) => s.id), ...props.tournament.organizers.map((o) => o.id)]);
            results.value = ((await res.json()).users ?? []).filter((u: { id: number }) => !taken.has(u.id));
        } finally {
            searching.value = false;
        }
    }, 250);
});

const add = async (user: { id: number; name: string }) => {
    error.value = null;
    const { ok, error: message } = await doModalAction(route('tournaments.scorers.add', props.tournament.uuid), 'POST', { user_id: user.id });
    if (!ok) {
        error.value = message;
        return;
    }
    toast.success(`${user.name} can now enter results`);
    query.value = '';
    results.value = [];
    emit('saved');
};

const remove = async (user: { id: number; name: string }) => {
    if (
        !(await confirm({
            title: `Remove ${user.name}?`,
            message: 'They will no longer be able to enter results.',
            confirmLabel: 'Remove',
            destructive: true,
        }))
    )
        return;
    const { ok } = await doModalAction(route('tournaments.scorers.remove', [props.tournament.uuid, user.id]), 'DELETE');
    if (ok) {
        toast.success(`${user.name} removed`);
        emit('saved');
    }
};
</script>

<template>
    <Card class="mb-4" data-testid="scorers-card">
        <CardContent class="space-y-3 p-4 sm:p-5">
            <div>
                <h2 class="flex items-center gap-1.5 text-sm font-semibold"><ClipboardCheck class="size-4" aria-hidden="true" /> Scorekeepers</h2>
                <p class="text-xs text-muted-foreground">
                    Let a judge or helper enter and correct results from the
                    <a :href="route('tournaments.score', tournament.uuid)" class="underline">scoring desk</a> without giving them the event.
                </p>
            </div>
            <ul v-if="scorers.length" class="divide-y rounded-md border">
                <li v-for="s in scorers" :key="s.id" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                    {{ s.name }}
                    <Button type="button" variant="ghost" size="sm" class="size-7 p-0" :aria-label="`Remove ${s.name}`" @click="remove(s)"
                        ><X class="size-4"
                    /></Button>
                </li>
            </ul>
            <div class="space-y-1.5">
                <Input v-model="query" placeholder="Search accounts by name…" aria-label="Search accounts to add as a scorekeeper" />
                <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
                <Loader2 v-if="searching" class="size-4 animate-spin text-muted-foreground" />
                <ul v-if="results.length" class="divide-y rounded-md border">
                    <li v-for="u in results" :key="u.id">
                        <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-accent" @click="add(u)">{{ u.name }}</button>
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
