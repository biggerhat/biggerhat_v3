<script setup lang="ts">
import BonanzaCardImage from '@/components/Bonanza/BonanzaCardImage.vue';
import BonanzaSplitCard from '@/components/Bonanza/BonanzaSplitCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head } from '@inertiajs/vue3';
import { Coins, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ActionTrigger {
    id: number;
    slug: string;
    name: string;
    suits?: string | null;
    stone_cost?: number;
    description?: string | null;
}

interface LootAction {
    id: number;
    slug: string;
    name: string;
    type?: string;
    is_signature?: boolean;
    stone_cost?: number;
    range?: number | null;
    range_type?: string | null;
    stat?: number | null;
    stat_suits?: string | null;
    stat_modifier?: string | null;
    resisted_by?: string | null;
    target_number?: number | null;
    target_suits?: string | null;
    damage?: number | string | null;
    description?: string | null;
    triggers?: ActionTrigger[];
    pivot?: { is_signature_action?: boolean };
}

interface LootAbility {
    id: number;
    slug: string;
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
}

interface LootTrigger {
    id: number;
    slug: string;
    name: string;
    suits?: string | null;
    stone_cost?: number;
    description?: string | null;
}

interface LootCardEntry {
    id: number;
    slug: string;
    suit: string;
    value: number | null;
    value_label: string;
    name: string;
    title_a: string | null;
    title_b: string | null;
    effect_a: string | null;
    effect_b: string | null;
    image: string | null;
    side_a_actions: LootAction[];
    side_b_actions: LootAction[];
    side_a_abilities: LootAbility[];
    side_b_abilities: LootAbility[];
    side_a_triggers: LootTrigger[];
    side_b_triggers: LootTrigger[];
}

const props = defineProps<{
    cards: LootCardEntry[];
}>();

const suitFilter = ref<'all' | 'crow' | 'mask' | 'ram' | 'tome' | 'joker'>('all');
const search = ref('');

const suitMeta: Record<string, { label: string; tone: string }> = {
    crow: { label: 'Crow', tone: 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-300' },
    mask: { label: 'Mask', tone: 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-300' },
    ram: { label: 'Ram', tone: 'border-red-500/50 bg-red-500/10 text-red-700 dark:text-red-300' },
    tome: { label: 'Tome', tone: 'border-blue-500/50 bg-blue-500/10 text-blue-700 dark:text-blue-300' },
    joker: { label: 'Joker', tone: 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-300' },
};

const matchesSearch = (c: LootCardEntry, q: string): boolean => {
    if (!q) return true;
    const haystacks: (string | null | undefined)[] = [
        c.name,
        c.title_a,
        c.title_b,
        c.effect_a,
        c.effect_b,
        ...c.side_a_actions.map((a) => a.name),
        ...c.side_b_actions.map((a) => a.name),
        ...c.side_a_abilities.map((a) => a.name),
        ...c.side_b_abilities.map((a) => a.name),
        ...c.side_a_triggers.map((t) => t.name),
        ...c.side_b_triggers.map((t) => t.name),
    ];
    return haystacks.some((h) => (h ?? '').toLowerCase().includes(q));
};

const filteredCards = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.cards.filter((c) => {
        if (suitFilter.value !== 'all' && c.suit !== suitFilter.value) return false;
        return matchesSearch(c, q);
    });
});

const cardsBySuit = computed(() => {
    const groups: Record<string, LootCardEntry[]> = {};
    for (const card of filteredCards.value) {
        groups[card.suit] = groups[card.suit] ?? [];
        groups[card.suit].push(card);
    }
    return groups;
});
</script>

<template>
    <Head title="Bonanza Loot Deck" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Bonanza Loot Deck" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Reference for the 54-card Loot Deck used in Bonanza Brawl. Each card has two sides — the player picks one when the card is
                    attached.
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <div class="flex flex-col gap-3 rounded-lg border bg-muted/30 p-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute left-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search cards, titles, or effect text…" class="h-9 pl-8 text-sm" />
                </div>
                <Tabs v-model="suitFilter" class="shrink-0">
                    <TabsList class="grid grid-cols-6">
                        <TabsTrigger value="all" class="text-xs">All</TabsTrigger>
                        <TabsTrigger value="crow" class="text-xs">Crows</TabsTrigger>
                        <TabsTrigger value="mask" class="text-xs">Masks</TabsTrigger>
                        <TabsTrigger value="ram" class="text-xs">Rams</TabsTrigger>
                        <TabsTrigger value="tome" class="text-xs">Tomes</TabsTrigger>
                        <TabsTrigger value="joker" class="text-xs">Jokers</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <EmptyState
                v-if="!filteredCards.length"
                :icon="Coins"
                title="No matching cards"
                description="Try a different suit filter or clear your search."
            />

            <template v-else>
                <div v-for="(group, suit) in cardsBySuit" :key="suit" class="space-y-2">
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <span
                            class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider"
                            :class="suitMeta[suit]?.tone ?? 'border-border bg-muted text-muted-foreground'"
                            >{{ suitMeta[suit]?.label ?? suit }}</span
                        >
                        <span class="text-xs font-normal text-muted-foreground">{{ group.length }} card{{ group.length === 1 ? '' : 's' }}</span>
                    </h2>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <template v-for="card in group" :key="card.id">
                            <BonanzaCardImage v-if="card.image" :image="card.image" :name="card.name" />
                            <BonanzaSplitCard
                                v-else
                                :name="card.name"
                                :suit="card.suit"
                                :value-label="card.value_label"
                                :image="null"
                                :side-a="{
                                    title: card.title_a,
                                    effect: card.effect_a,
                                    abilities: card.side_a_abilities,
                                    actions: card.side_a_actions,
                                    triggers: card.side_a_triggers,
                                }"
                                :side-b="{
                                    title: card.title_b,
                                    effect: card.effect_b,
                                    abilities: card.side_b_abilities,
                                    actions: card.side_b_actions,
                                    triggers: card.side_b_triggers,
                                }"
                            />
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
