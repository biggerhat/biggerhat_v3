<script setup lang="ts">
import ActionCard from '@/components/ActionCard.vue';
import { Badge } from '@/components/ui/badge';
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
    source_character_id: number | null;
    source_character_name?: string | null;
    triggers: TriggerData[];
}

const props = defineProps<{
    open: boolean;
    campaignId: number;
    crewShareCode: string;
    keyword1Id: number | null;
    keyword2Id: number | null;
    archetypeName: string;
    attackCap: number;
    tacticalCap: number;
    attackLimit: number;
    tacticalLimit: number;
    attackCurrentCount: number;
    tacticalCurrentCount: number;
    attackGetsTrigger: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'commit', actions: Array<ActionResult & { category: 'attack' | 'tactical' }>): void;
}>();

const confirmDialog = useConfirm();

const activeTab = ref<'attack' | 'tactical'>('attack');
const search = ref('');
const results = ref<ActionResult[]>([]);
const loading = ref(false);
// Keyed by source_id so the same action can't be staged twice; value carries
// which tab it was staged under (a picker session may touch both tabs).
const staged = ref<Map<number, ActionResult & { category: 'attack' | 'tactical' }>>(new Map());

const capForTab = (tab: 'attack' | 'tactical') => (tab === 'attack' ? props.attackCap : props.tacticalCap);
const limitForTab = (tab: 'attack' | 'tactical') => (tab === 'attack' ? props.attackLimit : props.tacticalLimit);
const currentCountForTab = (tab: 'attack' | 'tactical') => (tab === 'attack' ? props.attackCurrentCount : props.tacticalCurrentCount);
const stagedCountForTab = (tab: 'attack' | 'tactical') => [...staged.value.values()].filter((a) => a.category === tab).length;
const isAtLimit = (tab: 'attack' | 'tactical') => currentCountForTab(tab) + stagedCountForTab(tab) >= limitForTab(tab);

const fetchResults = async () => {
    if (!props.keyword1Id && !props.keyword2Id) {
        results.value = [];
        return;
    }
    loading.value = true;
    const url = new URL(route('campaigns.crews.leader.search.actions', [props.campaignId, props.crewShareCode]), window.location.origin);
    if (search.value) url.searchParams.set('q', search.value);
    url.searchParams.set('max_cost', String(capForTab(activeTab.value)));
    url.searchParams.set('type', activeTab.value);
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

watch([search, activeTab], () => debouncedFetch());
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            search.value = '';
            staged.value = new Map();
            activeTab.value = 'attack';
            fetchResults();
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
    if (isAtLimit(activeTab.value)) return;
    staged.value.set(a.source_id, { ...a, category: activeTab.value });
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
                Borrowed from an in-keyword ally (pg 17) — only that ally's own triggers carry over if this leader is
                <strong>Heavy Hitter</strong>; otherwise triggers are stripped from the borrowed action.
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
                <Badge variant="outline" class="text-[10px]">
                    {{ currentCountForTab(activeTab) + stagedCountForTab(activeTab) }} / {{ limitForTab(activeTab) }}
                </Badge>
                <Input v-model="search" placeholder="Search by name…" class="min-w-48 flex-1" />
            </div>

            <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                <p v-if="loading" class="py-6 text-center text-sm text-muted-foreground">Loading…</p>
                <p v-else-if="!results.length" class="py-6 text-center text-sm text-muted-foreground">
                    No {{ activeTab }} actions available from your crew's keywords.
                </p>
                <button
                    v-for="a in results"
                    :key="a.source_id ?? a.name"
                    type="button"
                    class="relative block w-full rounded-lg text-left ring-2 ring-transparent transition-colors"
                    :class="[
                        isStaged(a) ? 'ring-primary' : '',
                        !isStaged(a) && isAtLimit(activeTab) ? 'pointer-events-none opacity-40' : '',
                    ]"
                    @click="toggleStage(a)"
                >
                    <ActionCard :action="{ ...a, action_type: a.type }" :hide-footer="!a.source_character_name">
                        <template v-if="a.source_character_name" #footer>
                            <span class="text-muted-foreground">{{ a.source_character_name }}</span>
                        </template>
                    </ActionCard>
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
