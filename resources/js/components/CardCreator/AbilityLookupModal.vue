<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { useDebounceFn } from '@vueuse/core';
import { Check } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AbilityResult {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
}

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'commit', abilities: AbilityResult[]): void;
}>();

const confirmDialog = useConfirm();

const search = ref('');
const results = ref<AbilityResult[]>([]);
const loading = ref(false);
const staged = ref<Map<number, AbilityResult>>(new Map());

const fetchResults = async () => {
    if (search.value.length < 2) {
        results.value = [];
        return;
    }
    loading.value = true;
    try {
        const res = await fetch(route('api.card-creator.abilities') + '?q=' + encodeURIComponent(search.value));
        if (res.ok) results.value = await res.json();
    } finally {
        loading.value = false;
    }
};

const debouncedFetch = useDebounceFn(fetchResults, 250);

watch(search, () => debouncedFetch());
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            search.value = '';
            results.value = [];
            staged.value = new Map();
        }
    },
);

const isStaged = (a: AbilityResult) => a.source_id != null && staged.value.has(a.source_id);

const toggleStage = (a: AbilityResult) => {
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
            message: `You've selected ${staged.value.size} ability/ies that haven't been added yet. Close without adding them?`,
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
                <DialogTitle>Browse Abilities</DialogTitle>
            </DialogHeader>

            <p class="text-xs text-muted-foreground">Search the official catalog for an Ability to use as a starting point.</p>

            <Input v-model="search" placeholder="Search by name…" />

            <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                <p v-if="loading" class="py-6 text-center text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="search.length < 2" class="py-6 text-center text-sm text-muted-foreground">Type at least 2 characters to search.</p>
                <p v-else-if="!results.length" class="py-6 text-center text-sm text-muted-foreground">No abilities found.</p>
                <button
                    v-for="a in results"
                    :key="a.source_id ?? a.name"
                    type="button"
                    class="relative block w-full rounded-lg text-left ring-2 ring-transparent transition-colors"
                    :class="isStaged(a) ? 'ring-primary' : ''"
                    @click="toggleStage(a)"
                >
                    <AbilityCard :ability="a" hide-footer />
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
