<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface LootCardRow {
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
    sort_order: number;
    actions_count?: number;
    abilities_count?: number;
    triggers_count?: number;
}

const props = defineProps<{
    cards: LootCardRow[];
}>();

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.cards;
    return props.cards.filter((c) => c.name.toLowerCase().includes(q) || c.suit.toLowerCase().includes(q) || c.value_label.toLowerCase().includes(q));
});

const totalWithEffects = computed(() => props.cards.filter((c) => c.effect_a || c.effect_b).length);

const confirmDialog = useConfirm();
const deleteCard = async (card: LootCardRow) => {
    const ok = await confirmDialog({
        title: 'Delete Loot Card?',
        message: `Permanently remove "${card.name}" from the loot deck. This can't be undone.`,
        confirmLabel: 'Delete',
        destructive: true,
    });
    if (!ok) return;
    router.delete(route('admin.loot_cards.destroy', card.slug), { preserveScroll: true });
};
</script>

<template>
    <Head title="Bonanza Loot Cards · Admin" />

    <div class="container mx-auto space-y-4 p-4 lg:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">Bonanza Loot Cards</h1>
                <p class="text-sm text-muted-foreground">Fill in effect text from the Wyrd loot deck doc. The 54 rulebook cards are seeded; admins can also add homebrew cards.</p>
            </div>
            <div class="flex items-center gap-2">
                <Badge variant="secondary">{{ totalWithEffects }} / {{ cards.length }} cards have effects</Badge>
                <Link :href="route('admin.loot_cards.create')">
                    <Button size="sm" class="gap-1.5">
                        <Plus class="size-4" /> Add Card
                    </Button>
                </Link>
            </div>
        </div>

        <Card>
            <CardContent class="p-3">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search by name, suit, or value…" class="h-9 pl-8" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/30 text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 text-left">Card</th>
                            <th class="hidden px-3 py-2 text-left sm:table-cell">Suit</th>
                            <th class="hidden px-3 py-2 text-left sm:table-cell">Value</th>
                            <th class="px-3 py-2 text-left">Effects</th>
                            <th class="hidden px-3 py-2 text-left md:table-cell">Granted</th>
                            <th class="w-28 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in filtered" :key="c.id" class="border-b last:border-b-0 hover:bg-muted/20">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <img v-if="c.image" :src="`/storage/${c.image}`" :alt="c.name" class="size-8 shrink-0 rounded border object-cover" />
                                    <span class="font-medium">{{ c.name }}</span>
                                </div>
                            </td>
                            <td class="hidden px-3 py-2 capitalize sm:table-cell">{{ c.suit }}</td>
                            <td class="hidden px-3 py-2 font-mono text-xs sm:table-cell">{{ c.value_label }}</td>
                            <td class="px-3 py-2">
                                <span v-if="c.effect_a && c.effect_b" class="text-xs text-emerald-600 dark:text-emerald-400">A + B set</span>
                                <span v-else-if="c.effect_a || c.effect_b" class="text-xs text-amber-600 dark:text-amber-400">1 of 2 set</span>
                                <span v-else class="text-xs italic text-muted-foreground">Empty</span>
                            </td>
                            <td class="hidden px-3 py-2 text-xs text-muted-foreground md:table-cell">
                                <span v-if="(c.actions_count ?? 0) + (c.abilities_count ?? 0) + (c.triggers_count ?? 0) > 0">
                                    {{ c.abilities_count ?? 0 }}A · {{ c.actions_count ?? 0 }}Act · {{ c.triggers_count ?? 0 }}T
                                </span>
                                <span v-else class="italic">none</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="route('admin.loot_cards.edit', c.slug)">
                                        <Button size="sm" variant="ghost" class="h-7 gap-1 px-2 text-xs">
                                            <Pencil class="size-3.5" /> Edit
                                        </Button>
                                    </Link>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        title="Delete card"
                                        @click="deleteCard(c)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
