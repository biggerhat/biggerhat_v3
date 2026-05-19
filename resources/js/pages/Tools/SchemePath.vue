<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ChevronRight, GitBranch, RotateCcw, Share2, Sparkles, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Season {
    value: string;
    label: string;
}

interface SchemeItem {
    id: number;
    name: string;
    slug: string;
    selector: string | null;
    prerequisite: string | null;
    reveal: string | null;
    scoring: string | null;
    additional: string | null;
    image_url: string | null;
    next_scheme_ids: number[];
}

const props = defineProps<{
    season: Season;
    seasons: Season[];
    schemes: SchemeItem[];
}>();

const TURNS = 4;

const selectedSeason = ref(props.season.value);
const path = ref<number[]>([]);

const schemeById = computed(() => {
    const map = new Map<number, SchemeItem>();
    for (const s of props.schemes) map.set(s.id, s);
    return map;
});

const lookup = (id: number | null): SchemeItem | null => (id ? (schemeById.value.get(id) ?? null) : null);

const turnSchemes = computed(() => path.value.map((id) => lookup(id)).filter((s): s is SchemeItem => s !== null));

// Turn 1 = full season pool; subsequent turns = the previous scheme's
// next_scheme_ids resolved through the catalog map.
const candidatesForNextTurn = computed<SchemeItem[]>(() => {
    if (path.value.length === 0) return props.schemes;
    if (path.value.length >= TURNS) return [];
    const previous = lookup(path.value[path.value.length - 1]);
    if (!previous) return [];
    return previous.next_scheme_ids.map((id) => lookup(id)).filter((s): s is SchemeItem => s !== null);
});

const currentTurn = computed(() => path.value.length + 1);
const isComplete = computed(() => path.value.length >= TURNS);

const candidatesCount = computed(() => candidatesForNextTurn.value.length);
const { delays: candidateDelays } = useStaggeredEntry(candidatesCount);

const finalCount = computed(() => (isComplete.value ? TURNS : 0));
const { delays: finalDelays } = useStaggeredEntry(finalCount);

// Selector → GameIcon glyph type. Schemes use suit names ("Crows", "Tomes"
// etc.) as their selector; map to the lowercase singular for GameIcon.
const SUIT_MAP: Record<string, { icon: string; tint: string; ring: string }> = {
    crows: {
        icon: 'crow',
        tint: 'bg-green-500/10 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-500/40',
        ring: 'hover:ring-green-500/50',
    },
    masks: {
        icon: 'mask',
        tint: 'bg-purple-500/10 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/40',
        ring: 'hover:ring-purple-500/50',
    },
    rams: {
        icon: 'ram',
        tint: 'bg-red-500/10 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/40',
        ring: 'hover:ring-red-500/50',
    },
    tomes: {
        icon: 'tome',
        tint: 'bg-blue-500/10 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/40',
        ring: 'hover:ring-blue-500/50',
    },
};

const suitFor = (selector: string | null) => SUIT_MAP[(selector ?? '').toLowerCase()] ?? null;

const pickScheme = (scheme: SchemeItem) => {
    path.value = [...path.value, scheme.id];
    syncToUrl();
};

const truncateToTurn = (turnNumber: number) => {
    path.value = path.value.slice(0, turnNumber - 1);
    syncToUrl();
};

const reset = () => {
    path.value = [];
    syncToUrl();
};

const syncToUrl = () => {
    const params = new URLSearchParams(window.location.search);
    if (path.value.length) params.set('path', path.value.join(','));
    else params.delete('path');
    if (selectedSeason.value !== props.season.value) params.set('season', selectedSeason.value);
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.replaceState({}, '', newUrl);
};

const navigateToSeason = (season: string) => {
    router.get(route('tools.scheme_paths'), { season }, { only: ['season', 'schemes'], replace: true });
};

const shareLink = computed(() => {
    if (!path.value.length) return null;
    const params = new URLSearchParams();
    params.set('season', selectedSeason.value);
    params.set('path', path.value.join(','));
    return `${window.location.origin}${window.location.pathname}?${params.toString()}`;
});

const copied = ref(false);
const copyShareLink = async () => {
    if (!shareLink.value) return;
    try {
        await navigator.clipboard.writeText(shareLink.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        // clipboard blocked — no-op
    }
};

// Hydrate path from URL on mount so shared links round-trip. Skip ids the
// catalog doesn't recognize (stale link / season changed) rather than aborting.
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const raw = params.get('path');
    if (!raw) return;
    const ids = raw
        .split(',')
        .map((s) => Number(s.trim()))
        .filter((n) => Number.isFinite(n) && schemeById.value.has(n));
    path.value = ids.slice(0, TURNS);
});
</script>

<template>
    <Head :title="`Scheme Paths — ${season.label}`" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Scheme Paths">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">Plan a 4-turn scheme chain by walking the next-scheme graph turn by turn.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-4 sm:px-4 lg:px-6">
            <div class="grid gap-6 lg:grid-cols-8">
                <!-- Sidebar: season selector + summary + actions -->
                <aside class="lg:col-span-2">
                    <!-- Mobile: dropdown -->
                    <div class="mb-4 lg:hidden">
                        <Select v-model="selectedSeason" @update:model-value="(v) => navigateToSeason(v as string)">
                            <SelectTrigger class="w-full"><SelectValue placeholder="Select Season" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in seasons" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Desktop: sticky card -->
                    <div class="sticky top-6 hidden lg:block">
                        <Card>
                            <CardContent class="space-y-4 p-4">
                                <div>
                                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Season</h3>
                                    <nav class="flex flex-col gap-1">
                                        <button
                                            v-for="s in seasons"
                                            :key="s.value"
                                            class="rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                                            :class="s.value === season.value ? 'bg-accent font-medium' : 'text-muted-foreground'"
                                            @click="navigateToSeason(s.value)"
                                        >
                                            {{ s.label }}
                                        </button>
                                    </nav>
                                </div>

                                <div>
                                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Progress</h3>
                                    <div class="flex items-center gap-1">
                                        <div
                                            v-for="n in TURNS"
                                            :key="`step-${n}`"
                                            class="h-1.5 flex-1 rounded-full transition-colors"
                                            :class="path.length >= n ? 'bg-primary' : 'bg-muted'"
                                        />
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-muted-foreground">{{ path.length }} of {{ TURNS }} turns picked</p>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <Button v-if="shareLink" variant="outline" class="w-full gap-2" @click="copyShareLink">
                                        <Check v-if="copied" class="size-4 text-green-600 dark:text-green-400" />
                                        <Share2 v-else class="size-4" />
                                        {{ copied ? 'Copied!' : 'Share Path' }}
                                    </Button>
                                    <Button v-if="path.length" variant="ghost" class="w-full gap-2" @click="reset">
                                        <RotateCcw class="size-4" />
                                        Reset
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </aside>

                <!-- Content -->
                <div class="lg:col-span-6">
                    <!-- Mobile actions -->
                    <div v-if="path.length" class="mb-4 flex gap-2 lg:hidden">
                        <Button v-if="shareLink" variant="outline" class="flex-1 gap-2" @click="copyShareLink">
                            <Check v-if="copied" class="size-4 text-green-600 dark:text-green-400" />
                            <Share2 v-else class="size-4" />
                            {{ copied ? 'Copied!' : 'Share' }}
                        </Button>
                        <Button variant="ghost" class="gap-2" @click="reset">
                            <RotateCcw class="size-4" />
                            Reset
                        </Button>
                    </div>

                    <!-- Chosen path strip -->
                    <div v-if="path.length" class="mb-6 flex flex-wrap items-center gap-1.5">
                        <template v-for="(scheme, idx) in turnSchemes" :key="`step-${idx}`">
                            <ChevronRight v-if="idx > 0" class="size-3.5 shrink-0 text-muted-foreground/60" />
                            <button
                                type="button"
                                class="group inline-flex items-center gap-1.5 rounded-md border bg-card px-2.5 py-1.5 text-xs font-medium transition-colors hover:border-destructive/50 hover:bg-destructive/5"
                                :title="`Revise Turn ${idx + 1}`"
                                @click="truncateToTurn(idx + 1)"
                            >
                                <span class="rounded bg-primary/15 px-1 py-0 text-[10px] font-bold uppercase text-primary">T{{ idx + 1 }}</span>
                                <GameIcon
                                    v-if="suitFor(scheme.selector)"
                                    :type="suitFor(scheme.selector)!.icon"
                                    class-name="h-3.5 inline-block shrink-0"
                                />
                                <span class="truncate">{{ scheme.name }}</span>
                                <X class="size-3 shrink-0 text-muted-foreground transition-colors group-hover:text-destructive" />
                            </button>
                        </template>
                    </div>

                    <!-- Next-turn picker -->
                    <section v-if="!isComplete">
                        <div class="mb-3 flex items-baseline gap-2">
                            <span class="rounded bg-primary/15 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-primary"
                                >Turn {{ currentTurn }}</span
                            >
                            <h2 class="text-base font-semibold">
                                <template v-if="path.length === 0">Pick a starting scheme</template>
                                <template v-else>Pick a follow-up scheme</template>
                            </h2>
                            <span class="text-xs text-muted-foreground">
                                <template v-if="path.length === 0">{{ candidatesForNextTurn.length }} schemes in pool</template>
                                <template v-else>{{ candidatesForNextTurn.length }} branches available</template>
                            </span>
                        </div>

                        <EmptyState
                            v-if="candidatesForNextTurn.length === 0"
                            :icon="GitBranch"
                            title="Dead end"
                            description="No follow-up schemes from this branch. Click the chip above to revise an earlier turn."
                        />

                        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <button
                                v-for="(scheme, idx) in candidatesForNextTurn"
                                :key="scheme.id"
                                type="button"
                                class="animate-fade-in-up group relative flex flex-col overflow-hidden rounded-lg border bg-card text-left opacity-0 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1"
                                :class="suitFor(scheme.selector)?.ring ?? 'hover:ring-primary/50'"
                                :style="candidateDelays[idx]"
                                :title="scheme.name"
                                @click="pickScheme(scheme)"
                            >
                                <img
                                    v-if="scheme.image_url"
                                    :src="scheme.image_url"
                                    :alt="scheme.name"
                                    loading="lazy"
                                    class="block aspect-[5/7] w-full object-cover"
                                />
                                <!-- Fallback when no image_url: keep the picker dense
                                     with a stylized text card so the grid stays
                                     uniform. -->
                                <div
                                    v-else
                                    class="flex aspect-[5/7] w-full flex-col items-center justify-center gap-2 p-4 text-center"
                                    :class="suitFor(scheme.selector)?.tint ?? 'bg-muted/30'"
                                >
                                    <GameIcon v-if="suitFor(scheme.selector)" :type="suitFor(scheme.selector)!.icon" class-name="h-8 inline-block" />
                                    <div class="text-sm font-semibold">{{ scheme.name }}</div>
                                </div>

                                <!-- Always-on name banner; gives the user something
                                     to read on a glance whether the art is wordy
                                     or not. Sits on a translucent overlay so the
                                     image still dominates. -->
                                <div
                                    class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/85 via-black/65 to-transparent px-2.5 pb-2 pt-6 text-xs text-white"
                                >
                                    <div class="min-w-0 flex-1 truncate font-semibold">
                                        <span
                                            class="rounded bg-primary/80 px-1 py-0 text-[10px] font-bold uppercase tracking-wider text-primary-foreground"
                                            >T{{ currentTurn }}</span
                                        >
                                        <span class="ml-1.5">{{ scheme.name }}</span>
                                    </div>
                                    <Badge
                                        v-if="scheme.selector"
                                        variant="outline"
                                        class="inline-flex shrink-0 items-center gap-1 border-white/30 bg-black/50 px-1.5 py-0 text-[10px] text-white"
                                    >
                                        <GameIcon
                                            v-if="suitFor(scheme.selector)"
                                            :type="suitFor(scheme.selector)!.icon"
                                            class-name="h-3 inline-block"
                                        />
                                        {{ scheme.selector }}
                                    </Badge>
                                </div>

                                <!-- View-details corner link only appears on hover
                                     so the image stays clean by default. -->
                                <Link
                                    :href="route('schemes.view', scheme.slug)"
                                    class="absolute right-2 top-2 z-10 inline-flex items-center gap-0.5 rounded-md border border-white/30 bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white opacity-0 backdrop-blur transition-opacity hover:bg-black/80 group-hover:opacity-100"
                                    @click.stop
                                >
                                    Details
                                </Link>
                            </button>
                        </div>
                    </section>

                    <!-- Complete state — full path detail -->
                    <section v-else class="space-y-4">
                        <div
                            class="flex items-center gap-2 rounded-md border border-green-500/30 bg-green-500/5 p-3 text-sm text-green-700 dark:text-green-300"
                        >
                            <Sparkles class="size-4 shrink-0" />
                            <span>Full {{ TURNS }}-turn path planned. Click any step above to revise it, or share the link.</span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <Link
                                v-for="(scheme, idx) in turnSchemes"
                                :key="`detail-${idx}`"
                                :href="route('schemes.view', scheme.slug)"
                                class="animate-fade-in-up group relative block overflow-hidden rounded-lg border bg-card opacity-0 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                :style="finalDelays[idx]"
                            >
                                <img
                                    v-if="scheme.image_url"
                                    :src="scheme.image_url"
                                    :alt="scheme.name"
                                    loading="lazy"
                                    class="block aspect-[5/7] w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex aspect-[5/7] w-full flex-col items-center justify-center gap-2 p-4 text-center"
                                    :class="suitFor(scheme.selector)?.tint ?? 'bg-muted/30'"
                                >
                                    <GameIcon v-if="suitFor(scheme.selector)" :type="suitFor(scheme.selector)!.icon" class-name="h-8 inline-block" />
                                    <div class="text-sm font-semibold">{{ scheme.name }}</div>
                                </div>
                                <div
                                    class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/85 via-black/65 to-transparent px-2.5 pb-2 pt-6 text-xs text-white"
                                >
                                    <div class="min-w-0 flex-1 truncate font-semibold">
                                        <span
                                            class="rounded bg-primary/80 px-1 py-0 text-[10px] font-bold uppercase tracking-wider text-primary-foreground"
                                            >T{{ idx + 1 }}</span
                                        >
                                        <span class="ml-1.5">{{ scheme.name }}</span>
                                    </div>
                                    <Badge
                                        v-if="scheme.selector"
                                        variant="outline"
                                        class="inline-flex shrink-0 items-center gap-1 border-white/30 bg-black/50 px-1.5 py-0 text-[10px] text-white"
                                    >
                                        <GameIcon
                                            v-if="suitFor(scheme.selector)"
                                            :type="suitFor(scheme.selector)!.icon"
                                            class-name="h-3 inline-block"
                                        />
                                        {{ scheme.selector }}
                                    </Badge>
                                </div>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
