<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head } from '@inertiajs/vue3';
import { Coins, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface NamedRule {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    pivot?: { is_signature_action?: boolean };
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
    side_a_actions: NamedRule[];
    side_b_actions: NamedRule[];
    side_a_abilities: NamedRule[];
    side_b_abilities: NamedRule[];
    side_a_triggers: NamedRule[];
    side_b_triggers: NamedRule[];
}

const props = defineProps<{
    cards: LootCardEntry[];
}>();

const suitFilter = ref<'all' | 'crow' | 'mask' | 'ram' | 'tome' | 'joker'>('all');
const search = ref('');

// Per-rulebook flank-zone mapping. Surfaced inline so players see the suit /
// deployment-zone connection without flipping back to the rules card.
const suitMeta: Record<string, { label: string; tone: string }> = {
    crow: { label: 'Crow', tone: 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-300' },
    mask: { label: 'Mask', tone: 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-300' },
    ram: { label: 'Ram', tone: 'border-red-500/50 bg-red-500/10 text-red-700 dark:text-red-300' },
    tome: { label: 'Tome', tone: 'border-blue-500/50 bg-blue-500/10 text-blue-700 dark:text-blue-300' },
    joker: { label: 'Joker', tone: 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-300' },
};

const matchesSearch = (c: LootCardEntry, q: string): boolean => {
    if (!q) return true;
    const haystacks: (string | null)[] = [
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

const sideHasContent = (
    title: string | null,
    effect: string | null,
    actions: NamedRule[],
    abilities: NamedRule[],
    triggers: NamedRule[],
): boolean => !!title || !!effect || actions.length > 0 || abilities.length > 0 || triggers.length > 0;
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
                        >{{ suitMeta[suit]?.label ?? suit }}</span>
                        <span class="text-xs font-normal text-muted-foreground">{{ group.length }} card{{ group.length === 1 ? '' : 's' }}</span>
                    </h2>
                    <div class="grid gap-3 lg:grid-cols-2">
                        <Card v-for="card in group" :key="card.id" class="overflow-hidden">
                            <CardContent class="space-y-3 p-3">
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="card.image"
                                        :src="`/storage/${card.image}`"
                                        :alt="card.name"
                                        class="size-20 shrink-0 rounded-md border object-cover"
                                        loading="lazy"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                class="px-1.5 py-0 font-mono text-[10px] tabular-nums"
                                                :class="suitMeta[card.suit]?.tone ?? ''"
                                            >{{ card.value_label }}</Badge>
                                            <span class="truncate text-sm font-semibold">{{ card.name }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <!-- SIDE A -->
                                    <div
                                        v-if="sideHasContent(card.title_a, card.effect_a, card.side_a_actions, card.side_a_abilities, card.side_a_triggers)"
                                        class="space-y-1.5 rounded-md border bg-muted/30 p-2"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Badge class="bg-primary/15 px-1 py-0 text-[9px] font-bold text-primary">A</Badge>
                                            <span v-if="card.title_a" class="text-sm font-semibold">{{ card.title_a }}</span>
                                        </div>
                                        <p v-if="card.effect_a" class="whitespace-pre-line text-xs">{{ card.effect_a }}</p>
                                        <div v-if="card.side_a_abilities.length" class="space-y-0.5 pt-1">
                                            <div class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</div>
                                            <ul class="space-y-0.5 text-[11px]">
                                                <li v-for="a in card.side_a_abilities" :key="`a-ab-${a.id}`">
                                                    <span class="font-medium">{{ a.name }}</span><span v-if="a.description"> — <span class="text-muted-foreground">{{ a.description }}</span></span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div v-if="card.side_a_actions.length" class="space-y-0.5 pt-1">
                                            <div class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</div>
                                            <ul class="space-y-0.5 text-[11px]">
                                                <li v-for="a in card.side_a_actions" :key="`a-ac-${a.id}`">
                                                    <span class="font-medium">{{ a.name }}</span>
                                                    <Badge
                                                        v-if="a.pivot?.is_signature_action"
                                                        variant="outline"
                                                        class="ml-1 border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                                                    >Signature</Badge>
                                                </li>
                                            </ul>
                                        </div>
                                        <div v-if="card.side_a_triggers.length" class="space-y-0.5 pt-1">
                                            <div class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Triggers</div>
                                            <ul class="space-y-0.5 text-[11px]">
                                                <li v-for="t in card.side_a_triggers" :key="`a-tr-${t.id}`">
                                                    <span class="font-medium">{{ t.name }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div v-else class="rounded-md border border-dashed bg-muted/10 p-2 text-[11px] italic text-muted-foreground">
                                        Side A not yet entered
                                    </div>

                                    <!-- SIDE B -->
                                    <div
                                        v-if="sideHasContent(card.title_b, card.effect_b, card.side_b_actions, card.side_b_abilities, card.side_b_triggers)"
                                        class="space-y-1.5 rounded-md border bg-muted/30 p-2"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Badge class="bg-primary/15 px-1 py-0 text-[9px] font-bold text-primary">B</Badge>
                                            <span v-if="card.title_b" class="text-sm font-semibold">{{ card.title_b }}</span>
                                        </div>
                                        <p v-if="card.effect_b" class="whitespace-pre-line text-xs">{{ card.effect_b }}</p>
                                        <div v-if="card.side_b_abilities.length" class="space-y-0.5 pt-1">
                                            <div class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</div>
                                            <ul class="space-y-0.5 text-[11px]">
                                                <li v-for="a in card.side_b_abilities" :key="`b-ab-${a.id}`">
                                                    <span class="font-medium">{{ a.name }}</span><span v-if="a.description"> — <span class="text-muted-foreground">{{ a.description }}</span></span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div v-if="card.side_b_actions.length" class="space-y-0.5 pt-1">
                                            <div class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</div>
                                            <ul class="space-y-0.5 text-[11px]">
                                                <li v-for="a in card.side_b_actions" :key="`b-ac-${a.id}`">
                                                    <span class="font-medium">{{ a.name }}</span>
                                                    <Badge
                                                        v-if="a.pivot?.is_signature_action"
                                                        variant="outline"
                                                        class="ml-1 border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                                                    >Signature</Badge>
                                                </li>
                                            </ul>
                                        </div>
                                        <div v-if="card.side_b_triggers.length" class="space-y-0.5 pt-1">
                                            <div class="text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">Triggers</div>
                                            <ul class="space-y-0.5 text-[11px]">
                                                <li v-for="t in card.side_b_triggers" :key="`b-tr-${t.id}`">
                                                    <span class="font-medium">{{ t.name }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div v-else class="rounded-md border border-dashed bg-muted/10 p-2 text-[11px] italic text-muted-foreground">
                                        Side B not yet entered
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
