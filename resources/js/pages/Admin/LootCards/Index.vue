<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, FileText, Loader2, Pencil, Plus, RefreshCw, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
    pdf: { url: string | null; generated_at: number | null };
}>();

const search = ref('');

// ── Print PDF status (live via Reverb) ───────────────────────────────────
type PdfStatus = 'idle' | 'generating' | 'ready' | 'failed';
const pdfStatus = ref<PdfStatus>('idle');
const pdfUrl = ref<string | null>(props.pdf.url);
const pdfGeneratedAt = ref<number | null>(props.pdf.generated_at);
const pdfError = ref<string | null>(null);

const pdfGeneratedLabel = computed(() => {
    if (!pdfGeneratedAt.value) return 'never generated';
    return new Date(pdfGeneratedAt.value * 1000).toLocaleString();
});

// The cache path is versioned by *template* hash only, not by card content —
// a regenerate produces the exact same URL as before, served as a static
// asset (bypasses Laravel entirely, so no Cache-Control header can apply
// here). Without a cache-buster, "Download" would keep opening the browser's
// cached copy of that URL even after a successful regeneration.
const downloadUrl = computed(() => (pdfUrl.value && pdfGeneratedAt.value ? `${pdfUrl.value}?v=${pdfGeneratedAt.value}` : pdfUrl.value));

const regeneratePdf = () => {
    pdfStatus.value = 'generating';
    pdfError.value = null;
    router.post(route('admin.loot_cards.generate_pdf'), {}, { preserveScroll: true, preserveState: true });
};

interface PdfStatusEvent {
    status: PdfStatus;
    url: string | null;
    generated_at: number | null;
    message: string | null;
}

onMounted(() => {
    if (!window.Echo) return;
    window.Echo.channel('bonanza-deck').listen('.pdf.status', (e: PdfStatusEvent) => {
        pdfStatus.value = e.status;
        if (e.status === 'ready') {
            pdfUrl.value = e.url;
            pdfGeneratedAt.value = e.generated_at;
        }
        if (e.status === 'failed') {
            pdfError.value = e.message ?? 'PDF generation failed.';
        }
    });
});

onUnmounted(() => {
    window.Echo?.leave('bonanza-deck');
});

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

    <PageBanner title="Bonanza Loot Cards" class="mb-2">
        <template #subtitle>
            <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Fill in effect text from the Wyrd loot deck doc. The 54 rulebook cards are seeded; admins can also add homebrew cards.
                <Badge variant="secondary">{{ totalWithEffects }} / {{ cards.length }} cards have effects</Badge>
            </div>
        </template>
        <template #actions>
            <Link class="my-auto mr-2" :href="route('admin.loot_cards.create')">
                <Button size="sm" class="gap-1.5"> <Plus class="size-4" /> Add Card </Button>
            </Link>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 p-4 lg:p-6">

        <!-- Print deck PDF — server-rendered (headless Chrome), cached, refreshed on every card edit. -->
        <Card>
            <CardContent class="flex flex-wrap items-center justify-between gap-3 p-3">
                <div class="flex items-center gap-2.5">
                    <FileText class="size-5 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Print Deck PDF</p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="pdfStatus === 'generating'" class="inline-flex items-center gap-1 text-amber-600">
                                <Loader2 class="size-3 animate-spin" /> Generating…
                            </span>
                            <span v-else-if="pdfStatus === 'failed'" class="text-rose-600">{{ pdfError }}</span>
                            <span v-else>Last generated: {{ pdfGeneratedLabel }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="pdfUrl" :href="downloadUrl" target="_blank" rel="noopener">
                        <Button size="sm" variant="outline" class="gap-1.5"> <Download class="size-4" /> Download </Button>
                    </a>
                    <Button size="sm" variant="outline" class="gap-1.5" :disabled="pdfStatus === 'generating'" @click="regeneratePdf">
                        <RefreshCw class="size-4" :class="pdfStatus === 'generating' ? 'animate-spin' : ''" /> Regenerate PDF
                    </Button>
                </div>
            </CardContent>
        </Card>

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
                                    <img
                                        v-if="c.image"
                                        :src="`/storage/${c.image}`"
                                        :alt="c.name"
                                        class="size-8 shrink-0 rounded border object-cover"
                                    />
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
                                        <Button size="sm" variant="ghost" class="h-7 gap-1 px-2 text-xs"> <Pencil class="size-3.5" /> Edit </Button>
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
