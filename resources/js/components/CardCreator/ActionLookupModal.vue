<script setup lang="ts">
import ActionCard from '@/components/ActionCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { useDebounceFn } from '@vueuse/core';
import { Check } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
    source_id: number | null;
}

interface ActionResult {
    name: string;
    type: string;
    is_signature: boolean;
    stone_cost: number;
    range: number | string | null;
    range_type: string | null;
    stat: number | string | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | string | null;
    target_suits: string | null;
    damage: string | null;
    description: string | null;
    source_id: number | null;
    triggers: TriggerData[];
}

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'commit', actions: ActionResult[]): void;
}>();

const confirmDialog = useConfirm();

const activeTab = ref<'attack' | 'tactical'>('attack');
const search = ref('');
const results = ref<ActionResult[]>([]);
const loading = ref(false);
// Keyed by source_id so the same action can't be staged twice; a picker
// session may touch both tabs, so entries just carry their own type.
const staged = ref<Map<number, ActionResult>>(new Map());

const fetchResults = async () => {
    if (search.value.length < 2) {
        results.value = [];
        return;
    }
    loading.value = true;
    const url = new URL(route('api.card-creator.actions'), window.location.origin);
    url.searchParams.set('q', search.value);
    url.searchParams.set('type', activeTab.value);
    try {
        const res = await fetch(url.toString());
        if (res.ok) results.value = await res.json();
    } finally {
        loading.value = false;
    }
};

const debouncedFetch = useDebounceFn(fetchResults, 250);

watch([search, activeTab], () => debouncedFetch());
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            search.value = '';
            results.value = [];
            staged.value = new Map();
            activeTab.value = 'attack';
        }
    },
);

const isStaged = (a: ActionResult) => a.source_id != null && staged.value.has(a.source_id);

const toggleStage = (a: ActionResult) => {
    if (a.source_id == null) return;
    if (staged.value.has(a.source_id)) {
        staged.value.delete(a.source_id);
        staged.value = new Map(staged.value);
        return;
    }
    staged.value.set(a.source_id, a);
    staged.value = new Map(staged.value);
};

const stagedCount = computed(() => staged.value.size);

const commit = () => {
    emit('commit', [...staged.value.values()]);
    staged.value = new Map();
    emit('update:open', false);
};

const attemptClose = async (nextOpen: boolean) => {
    if (nextOpen) {
        emit('update:open', true);
        return;
    }
    if (staged.value.size > 0) {
        const confirmed = await confirmDialog({
            title: 'Discard selections?',
            message: `You've selected ${staged.value.size} action(s) that haven't been added yet. Close without adding them?`,
            confirmLabel: 'Discard',
            destructive: true,
        });
        if (!confirmed) return;
        staged.value = new Map();
    }
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="attemptClose">
        <DialogScrollContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Browse Actions</DialogTitle>
            </DialogHeader>

            <p class="text-xs text-muted-foreground">
                Search the official catalog for an Action to use as a starting point — triggers aren't copied over, add those separately below.
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-md border p-0.5">
                    <button
                        type="button"
                        class="rounded px-3 py-1 text-sm"
                        :class="activeTab === 'attack' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                        @click="activeTab = 'attack'"
                    >
                        Attack
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-1 text-sm"
                        :class="activeTab === 'tactical' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                        @click="activeTab = 'tactical'"
                    >
                        Tactical
                    </button>
                </div>
                <Input v-model="search" placeholder="Search by name…" class="min-w-48 flex-1" />
            </div>

            <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                <p v-if="loading" class="py-6 text-center text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="search.length < 2" class="py-6 text-center text-sm text-muted-foreground">Type at least 2 characters to search.</p>
                <p v-else-if="!results.length" class="py-6 text-center text-sm text-muted-foreground">No {{ activeTab }} actions found.</p>
                <button
                    v-for="a in results"
                    :key="a.source_id ?? a.name"
                    type="button"
                    class="relative block w-full rounded-lg text-left ring-2 ring-transparent transition-colors"
                    :class="isStaged(a) ? 'ring-primary' : ''"
                    @click="toggleStage(a)"
                >
                    <ActionCard :action="{ ...a, action_type: a.type }" hide-footer />
                    <div v-if="isStaged(a)" class="absolute right-2 top-2 rounded-full bg-primary p-1 text-primary-foreground shadow">
                        <Check class="size-3.5" />
                    </div>
                </button>
            </div>

            <DialogFooter class="items-center justify-between sm:justify-between">
                <span class="text-xs text-muted-foreground">{{ stagedCount }} selected</span>
                <Button :disabled="stagedCount === 0" @click="commit">Add {{ stagedCount > 0 ? `(${stagedCount})` : '' }}</Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
