<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookMarked,
    BookOpen,
    Crown,
    Dice6,
    FileDown,
    Library,
    Newspaper,
    Package,
    Radio,
    RefreshCw,
    ScrollText,
    Search,
    Shuffle,
    Skull,
    Sparkles,
    Sword,
    Swords,
    Target,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const factions = computed(() => page.props.faction_info);
const isLoggedIn = computed(() => !!page.props.auth?.user);

const props = defineProps<{
    featured_character?: any;
    recent_crews?: any[];
    recent_articles?: any[];
    recent_transmissions?: any[];
    faction_counts?: Record<string, number>;
    station_counts?: Record<string, number>;
    stats: { characters: number; keywords: number; miniatures: number };
}>();

const factionEntries = computed(() => Object.entries(factions.value ?? {}));
const factionCount = (key: string): number => props.faction_counts?.[key] ?? 0;

// Station shortcuts to advanced search
const stationLinks = [
    { label: 'Masters', value: 'master', icon: Crown, color: 'text-amber-500' },
    { label: 'Minions', value: 'minion', icon: Sword, color: 'text-emerald-500' },
    { label: 'Peons', value: 'peon', icon: Skull, color: 'text-purple-500' },
];

const stationCount = (value: string): number => props.station_counts?.[value] ?? 0;

const stationUrl = (value: string) => route('search.view') + '?station=' + value;
</script>

<template>
    <Head title="Home" />
    <div class="relative w-full max-w-full overflow-x-hidden">
        <!-- Background gradient -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[32rem] opacity-[0.06] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto flex w-full max-w-full flex-col gap-12 overflow-x-hidden px-3 pb-12 pt-6 sm:px-4 lg:gap-16">
            <!-- ═══ Hero ═══ -->
            <div class="animate-fade-in-up flex flex-col items-center pt-4 sm:pt-8 lg:pt-12">
                <img src="/images/hat_side.webp" class="h-32 sm:h-40 md:h-48" alt="BiggerHat.net" fetchpriority="high" />
                <h1 class="mt-4 text-center text-2xl font-bold tracking-tight sm:text-3xl md:text-4xl">
                    Your Malifaux Companion
                </h1>
                <p class="mt-2 max-w-2xl text-center text-sm text-muted-foreground sm:text-base">
                    Browse the entire database, build crews, track your collection, and play your games — all in one place.
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2 sm:gap-3">
                    <Link :href="route('search.view')">
                        <Button class="gap-2">
                            <Search class="size-4" />
                            <span>Browse Database</span>
                        </Button>
                    </Link>
                    <Link :href="route('tools.crew_builder.index')">
                        <Button variant="outline" class="gap-2">
                            <Swords class="size-4" />
                            <span>Build a Crew</span>
                        </Button>
                    </Link>
                </div>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground sm:gap-x-5 sm:text-xs">
                    <span><strong class="text-foreground">{{ stats.characters }}</strong> characters</span>
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">{{ stats.miniatures }}</strong> miniatures</span>
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">{{ stats.keywords }}</strong> keywords</span>
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">8</strong> factions</span>
                </div>
            </div>

            <!-- ═══ Faction Tiles ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 80ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Factions</h2>
                </div>
                <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                    <Link
                        v-for="([key, faction]) in factionEntries"
                        :key="key"
                        :href="route('factions.view', key)"
                        class="group flex flex-col items-center gap-1 rounded-lg border border-transparent p-2 transition-all duration-200 hover:-translate-y-1 hover:border-border hover:bg-muted hover:shadow-md sm:p-3"
                    >
                        <img :src="faction.logo" :alt="faction.name" class="size-10 transition-transform group-hover:scale-105 sm:size-12 md:size-14" loading="lazy" decoding="async" />
                        <span class="text-center text-[10px] font-medium leading-tight sm:text-xs">{{ faction.name }}</span>
                        <span class="text-[9px] text-muted-foreground sm:text-[10px]">{{ factionCount(key as string) }} models</span>
                    </Link>
                </div>
            </div>

            <!-- ═══ Start Here: 3 Big CTA Cards ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 120ms">
                <div class="mb-3 px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Start Here</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Crew Builder -->
                    <Link
                        :href="route('tools.crew_builder.index')"
                        class="group relative overflow-hidden rounded-xl border-2 border-primary/20 bg-gradient-to-br from-primary/10 via-primary/5 to-transparent p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                <Swords class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold leading-tight group-hover:text-primary">Crew Builder</h3>
                                <p class="mt-1 text-xs text-muted-foreground">Build, save, and share crews. PDF export, soulstone tracking, and reference cards built in.</p>
                                <div class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                    Open builder <ArrowRight class="size-3" />
                                </div>
                            </div>
                        </div>
                    </Link>

                    <!-- Game Tracker -->
                    <Link
                        :href="isLoggedIn ? route('games.index') : route('login')"
                        class="group relative overflow-hidden rounded-xl border-2 border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-500/50 hover:shadow-lg"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white dark:text-emerald-400">
                                <Target class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold leading-tight group-hover:text-emerald-600 dark:group-hover:text-emerald-400">Game Tracker</h3>
                                <p class="mt-1 text-xs text-muted-foreground">Track health, activations, schemes, and VP. Solo or multiplayer with live updates.</p>
                                <div class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-emerald-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-emerald-400">
                                    {{ isLoggedIn ? 'Start a game' : 'Sign in to play' }} <ArrowRight class="size-3" />
                                </div>
                            </div>
                        </div>
                    </Link>

                    <!-- Collection / Wishlist -->
                    <Link
                        :href="isLoggedIn ? route('collection.index') : route('login')"
                        class="group relative overflow-hidden rounded-xl border-2 border-amber-500/20 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-500/50 hover:shadow-lg sm:col-span-2 lg:col-span-1"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 transition-colors group-hover:bg-amber-500 group-hover:text-white dark:text-amber-400">
                                <BookMarked class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold leading-tight group-hover:text-amber-600 dark:group-hover:text-amber-400">Collection & Wishlist</h3>
                                <p class="mt-1 text-xs text-muted-foreground">Track owned, built, and painted models. Create wishlists by keyword or package.</p>
                                <div class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-amber-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-amber-400">
                                    {{ isLoggedIn ? 'My collection' : 'Sign in to track' }} <ArrowRight class="size-3" />
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ═══ Featured Character Spotlight ═══ -->
            <section v-if="featured_character && featured_character.standard_miniatures?.length" class="animate-fade-in-up w-full min-w-0" style="animation-delay: 160ms; max-width: 100%;">
                <div class="w-full min-w-0 overflow-hidden rounded-xl border bg-card p-4 sm:p-6" style="max-width: 100%;">
                    <!-- Card -->
                    <div class="mx-auto mb-5 max-w-full overflow-hidden" style="width: 10rem;">
                        <CharacterCardView :miniature="featured_character.standard_miniatures[0]" :character-slug="featured_character.slug" />
                    </div>
                    <!-- Info -->
                    <div class="min-w-0 text-center">
                        <div class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-primary">
                            <Sparkles class="size-3" />
                            Featured Character
                        </div>
                        <h2 class="break-words text-xl font-bold sm:text-2xl">{{ featured_character.display_name }}</h2>
                        <p v-if="featured_character.title" class="break-words text-sm text-muted-foreground">{{ featured_character.title }}</p>
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                            <Badge variant="outline" class="text-[10px] capitalize">{{ featured_character.faction_label || featured_character.faction }}</Badge>
                            <Badge v-if="featured_character.station" variant="secondary" class="text-[10px] capitalize">{{ featured_character.station }}</Badge>
                            <Badge v-for="kw in (featured_character.keywords ?? []).slice(0, 3)" :key="kw.id" variant="outline" class="text-[10px]">{{ kw.name }}</Badge>
                        </div>
                        <div class="mx-auto mt-4 grid w-full max-w-xs grid-cols-3 gap-2 text-center">
                            <div class="rounded-md bg-muted px-2 py-2">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Cost</div>
                                <div class="text-base font-bold">{{ featured_character.cost ?? '—' }}</div>
                            </div>
                            <div class="rounded-md bg-muted px-2 py-2">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Health</div>
                                <div class="text-base font-bold">{{ featured_character.health ?? '—' }}</div>
                            </div>
                            <div class="rounded-md bg-muted px-2 py-2">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Defense</div>
                                <div class="text-base font-bold">{{ featured_character.defense ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                            <Link
                                :href="route('characters.view', { character: featured_character.slug, miniature: featured_character.standard_miniatures[0].id, slug: featured_character.standard_miniatures[0].slug })"
                            >
                                <Button size="sm" class="gap-1.5"><span>View Character</span><ArrowRight class="size-3.5" /></Button>
                            </Link>
                            <Button variant="ghost" size="sm" class="gap-1.5" @click="router.reload({ only: ['featured_character'] })">
                                <RefreshCw class="size-3.5" />
                                Shuffle
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ Discover ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 200ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Discover</h2>
                    <Link :href="route('search.view')" class="text-xs text-primary hover:underline">Advanced search</Link>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <!-- By Station -->
                    <Card>
                        <CardContent class="p-4">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">By Station</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <Link
                                    v-for="s in stationLinks"
                                    :key="s.value"
                                    :href="stationUrl(s.value)"
                                    class="group flex flex-col items-center gap-1 overflow-hidden rounded-md border px-2 py-3 text-center transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm"
                                >
                                    <component :is="s.icon" class="size-5 shrink-0" :class="s.color" />
                                    <div class="truncate text-xs font-medium leading-tight group-hover:text-primary">{{ s.label }}</div>
                                    <div class="text-[10px] text-muted-foreground">{{ stationCount(s.value) }}</div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Schemes & Strategies -->
                    <Link
                        :href="route('seasons.index')"
                        class="group block overflow-hidden rounded-lg border bg-card transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-3 p-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <ScrollText class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold leading-tight group-hover:text-primary">Schemes &amp; Strategies</h3>
                                <p class="mt-1 text-xs text-muted-foreground">Browse the current season's tactical cards.</p>
                            </div>
                            <ArrowRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                        </div>
                    </Link>

                    <!-- Surprise Me -->
                    <Link
                        :href="route('characters.random')"
                        class="group block overflow-hidden rounded-lg border bg-card transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-3 p-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-pink-500/10 text-pink-600 dark:text-pink-400">
                                <Shuffle class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold leading-tight group-hover:text-primary">Surprise Me</h3>
                                <p class="mt-1 text-xs text-muted-foreground">Jump to a random character page.</p>
                            </div>
                            <ArrowRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ═══ Community Hub ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 240ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">From the Community</h2>
                </div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Articles -->
                    <div v-if="recent_articles?.length">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                                <Newspaper class="size-4 text-muted-foreground" />
                                Latest Articles
                            </h3>
                            <Link :href="route('blog.index')" class="text-[11px] text-primary hover:underline">All</Link>
                        </div>
                        <div class="space-y-1.5">
                            <Link
                                v-for="article in recent_articles"
                                :key="article.slug"
                                :href="route('blog.view', article.slug)"
                                class="group block overflow-hidden rounded-lg border px-3 py-2 transition-all hover:border-primary/30 hover:bg-muted/50"
                            >
                                <p class="line-clamp-2 break-words text-sm font-medium leading-snug group-hover:text-primary">{{ article.title }}</p>
                                <div class="mt-1 flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                    <span v-if="article.category">{{ article.category }}</span>
                                    <span v-if="article.category">·</span>
                                    <span>{{ article.published_at }}</span>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Transmissions -->
                    <div v-if="recent_transmissions?.length">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                                <Radio class="size-4 text-muted-foreground" />
                                Aethervox
                            </h3>
                            <Link :href="route('channels.index')" class="text-[11px] text-primary hover:underline">All</Link>
                        </div>
                        <div class="space-y-1.5">
                            <Link
                                v-for="t in recent_transmissions"
                                :key="t.id"
                                :href="route('channels.view', t.channel_slug)"
                                class="group flex items-start gap-2 rounded-lg border px-3 py-2 transition-all hover:border-primary/30 hover:bg-muted/50"
                            >
                                <img
                                    v-if="t.channel_image"
                                    :src="'/storage/' + t.channel_image"
                                    :alt="t.channel_name"
                                    class="mt-0.5 size-7 shrink-0 rounded-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <Radio v-else class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                <div class="min-w-0 flex-1 overflow-hidden">
                                    <p class="line-clamp-2 break-words text-sm font-medium leading-snug group-hover:text-primary">{{ t.title }}</p>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                        <span class="truncate">{{ t.channel_name }}</span>
                                        <span>·</span>
                                        <span class="shrink-0">{{ t.release_date }}</span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Recent Crews -->
                    <div v-if="recent_crews?.length">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                                <Users class="size-4 text-muted-foreground" />
                                Recent Crews
                            </h3>
                            <Link :href="route('tools.crew_builder.index')" class="text-[11px] text-primary hover:underline">All</Link>
                        </div>
                        <div class="space-y-1.5">
                            <Link
                                v-for="crew in recent_crews.slice(0, 4)"
                                :key="crew.id"
                                :href="route('tools.crew_builder.share', crew.share_code)"
                                class="group flex items-start gap-2 overflow-hidden rounded-lg border px-3 py-2 transition-all hover:border-primary/30 hover:bg-muted/50"
                            >
                                <FactionLogo :faction="crew.faction" class-name="size-6 shrink-0 mt-0.5" />
                                <div class="min-w-0 flex-1 overflow-hidden">
                                    <p class="truncate text-sm font-medium leading-snug group-hover:text-primary">{{ crew.name }}</p>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-1 text-[10px] text-muted-foreground">
                                        <span class="truncate">{{ crew.master_name || crew.faction_label }}</span>
                                        <span>·</span>
                                        <span class="shrink-0">{{ crew.encounter_size }}ss</span>
                                        <span>·</span>
                                        <span class="truncate">{{ crew.user_name }}</span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Tools Grid ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 280ms">
                <div class="mb-3 px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">More Tools</h2>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-7">
                    <Link
                        :href="route('search.view')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <Search class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">Advanced Search</span>
                    </Link>
                    <Link
                        :href="route('tools.compare')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <Library class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">Compare</span>
                    </Link>
                    <Link
                        :href="route('keywords.index')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <BookOpen class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">Keywords</span>
                    </Link>
                    <Link
                        :href="route('packages.index')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <Package class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">Packages</span>
                    </Link>
                    <Link
                        :href="route('tools.scenario_generator')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <Dice6 class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">Scenarios</span>
                    </Link>
                    <Link
                        :href="route('tools.pdf.index')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <FileDown class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">PDF Cards</span>
                    </Link>
                    <Link
                        :href="route('lores.index')"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                    >
                        <BookMarked class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                        <span class="text-[11px] font-medium leading-tight">Lore</span>
                    </Link>
                </div>
            </div>

            <!-- ═══ Footer ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 320ms">
                <Separator class="mb-4" />
                <div class="flex flex-wrap items-center justify-center gap-3 text-xs text-muted-foreground sm:gap-6">
                    <span>{{ stats.characters }} Characters</span>
                    <span>{{ stats.miniatures }} Miniatures</span>
                    <span>{{ stats.keywords }} Keywords</span>
                    <span>8 Factions</span>
                </div>
            </div>
        </div>
    </div>
</template>
