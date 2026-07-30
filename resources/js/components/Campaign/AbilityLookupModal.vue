<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import { Badge } from '@/components/ui/badge';
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
    source_character_id: number | null;
    source_character_name?: string | null;
}

const props = defineProps<{
    open: boolean;
    campaignId: number;
    crewShareCode: string;
    keyword1Id: number | null;
    keyword2Id: number | null;
    abilityCap: number;
    abilityLimit: number;
    abilityCurrentCount: number;
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

const isAtLimit = computed(() => props.abilityCurrentCount + staged.value.size >= props.abilityLimit);

const fetchResults = async () => {
    if (!props.keyword1Id && !props.keyword2Id) {
        results.value = [];
        return;
    }
    loading.value = true;
    const url = new URL(route('campaigns.crews.leader.search.abilities', [props.campaignId, props.crewShareCode]), window.location.origin);
    if (search.value) url.searchParams.set('q', search.value);
    url.searchParams.set('max_cost', String(props.abilityCap));
    if (props.keyword1Id) url.searchParams.set('keyword_1_id', String(props.keyword1Id));
    if (props.keyword2Id) url.searchParams.set('keyword_2_id', String(props.keyword2Id));
    try {
        const res = await fetch(url.toString());
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
            staged.value = new Map();
            fetchResults();
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
    if (isAtLimit.value) return;
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

            <p class="text-xs text-muted-foreground">Borrowed from an in-keyword ally (pg 17), capped by that ally's own cost.</p>

            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline" class="text-[10px]">{{ abilityCurrentCount + stagedCount }} / {{ abilityLimit }}</Badge>
                <Input v-model="search" placeholder="Search by name…" class="min-w-48 flex-1" />
            </div>

            <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                <p v-if="loading" class="py-6 text-center text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="!results.length" class="py-6 text-center text-sm text-muted-foreground">
                    No abilities available from your crew's keywords.
                </p>
                <button
                    v-for="a in results"
                    :key="a.source_id ?? a.name"
                    type="button"
                    class="relative block w-full rounded-lg text-left ring-2 ring-transparent transition-colors"
                    :class="[isStaged(a) ? 'ring-primary' : '', !isStaged(a) && isAtLimit ? 'pointer-events-none opacity-40' : '']"
                    @click="toggleStage(a)"
                >
                    <AbilityCard :ability="a" :hide-footer="!a.source_character_name">
                        <template v-if="a.source_character_name" #footer>
                            <span class="text-muted-foreground">{{ a.source_character_name }}</span>
                        </template>
                    </AbilityCard>
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
