<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import JsonLd from '@/components/JsonLd.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { factionBackground } from '@/composables/useFactionColor';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, BookMarked, Lock, Megaphone, Newspaper, Radio, RefreshCw, Search, Sparkles, Swords, Target, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth?.user);

defineProps<{
    featured_character?: any;
    recent_crews?: any[];
    recent_articles?: any[];
    recent_news?: any[];
    recent_transmissions?: any[];
    stats: { characters: number; keywords: number; miniatures: number };
}>();

// One fixed slot per Community Activity feed (Articles / Site News / Aethervox / Recent Crews).
const { delays: feedDelays } = useStaggeredEntry(ref(4), 60);
</script>

<template>
    <SeoHead
        title="BiggerHat — Malifaux &amp; The Other Side database"
        description="The comprehensive Malifaux and The Other Side database. Browse every character, upgrade, keyword, faction, and lore entry. Build crews, run tournaments, and find anything for either game."
        type="website"
    />
    <!-- WebSite + sitelinks search box. Tells Google to expose a search box
         underneath the result when users query "biggerhat". -->
    <JsonLd
        head-key="website"
        :data="{
            '@context': 'https://schema.org',
            '@type': 'WebSite',
            name: 'BiggerHat',
            url: 'https://biggerhat.net/',
            potentialAction: {
                '@type': 'SearchAction',
                target: {
                    '@type': 'EntryPoint',
                    urlTemplate: 'https://biggerhat.net/advanced?q={search_term_string}',
                },
                'query-input': 'required name=search_term_string',
            },
        }"
    />
    <JsonLd
        head-key="organization"
        :data="{
            '@context': 'https://schema.org',
            '@type': 'Organization',
            name: 'BiggerHat',
            url: 'https://biggerhat.net/',
            logo: 'https://biggerhat.net/images/biggerhat-og.png',
        }"
    />
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
                <h1 class="mt-4 text-balance text-center text-2xl font-bold tracking-tight sm:text-3xl md:text-4xl">
                    Your Companion for Malifaux and The Other Side
                </h1>
                <p class="mt-2 max-w-2xl text-center text-sm text-muted-foreground sm:text-base">
                    Browse the full card database, build and share crews, and track your games — for both games, in one place.
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
                    <span
                        ><strong class="text-foreground">{{ stats.characters }}</strong> characters</span
                    >
                    <span class="opacity-50">·</span>
                    <span
                        ><strong class="text-foreground">{{ stats.miniatures }}</strong> miniatures</span
                    >
                    <span class="opacity-50">·</span>
                    <span
                        ><strong class="text-foreground">{{ stats.keywords }}</strong> keywords</span
                    >
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">8</strong> factions</span>
                </div>
            </div>

            <!-- ═══ Start Here: 3 Big CTA Cards ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 80ms">
                <div class="mb-3 px-1">
                    <HeadingEyebrow as="h2">Start Here</HeadingEyebrow>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Crew Builder -->
                    <Link
                        :href="route('tools.crew_builder.index')"
                        class="group relative overflow-hidden rounded-xl border-2 border-primary/20 bg-gradient-to-br from-primary/10 via-primary/5 to-transparent p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                            >
                                <Swords class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold leading-tight group-hover:text-primary">Crew Builder</h3>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Build, save, and share crews. PDF export, soulstone tracking, and reference cards built in.
                                </p>
                                <div
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100"
                                >
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
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white dark:text-emerald-400"
                            >
                                <Target class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold leading-tight group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                                    Game Tracker
                                </h3>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Track health, activations, schemes, and VP. Solo or multiplayer with live updates.
                                </p>
                                <div
                                    v-if="isLoggedIn"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-emerald-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-emerald-400"
                                >
                                    Start a game <ArrowRight class="size-3" />
                                </div>
                                <div
                                    v-else
                                    class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400"
                                >
                                    <Lock class="size-3" />
                                    Sign in to get started
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
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 transition-colors group-hover:bg-amber-500 group-hover:text-white dark:text-amber-400"
                            >
                                <BookMarked class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold leading-tight group-hover:text-amber-600 dark:group-hover:text-amber-400">
                                    Collection & Wishlist
                                </h3>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Track owned, built, and painted models. Create wishlists by keyword or package.
                                </p>
                                <div
                                    v-if="isLoggedIn"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-amber-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-amber-400"
                                >
                                    My collection <ArrowRight class="size-3" />
                                </div>
                                <div
                                    v-else
                                    class="mt-2 inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:text-amber-400"
                                >
                                    <Lock class="size-3" />
                                    Sign in to get started
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ═══ Featured Character Spotlight ═══ -->
            <section
                v-if="featured_character && featured_character.standard_miniatures?.length"
                class="animate-fade-in-up w-full min-w-0"
                style="animation-delay: 140ms; max-width: 100%"
            >
                <p class="mb-3 px-1 text-xs text-muted-foreground">New to Malifaux? Meet a random model from the roster.</p>
                <div class="w-full min-w-0 overflow-hidden rounded-xl border bg-card p-4 sm:p-6" style="max-width: 100%">
                    <!-- Card -->
                    <div class="mx-auto mb-5 max-w-full overflow-hidden" style="width: 10rem">
                        <CharacterCardView :miniature="featured_character.standard_miniatures[0]" :character-slug="featured_character.slug" />
                    </div>
                    <!-- Info -->
                    <div class="min-w-0 text-center">
                        <div
                            class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-primary"
                        >
                            <Sparkles class="size-3" />
                            Featured Character
                        </div>
                        <h2 class="break-words text-xl font-bold sm:text-2xl">{{ featured_character.display_name }}</h2>
                        <p v-if="featured_character.title" class="break-words text-sm text-muted-foreground">{{ featured_character.title }}</p>
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5">
                            <Badge variant="outline" class="text-[10px] capitalize">{{
                                featured_character.faction_label || featured_character.faction
                            }}</Badge>
                            <Badge v-if="featured_character.station" variant="secondary" class="text-[10px] capitalize">{{
                                featured_character.station
                            }}</Badge>
                            <Badge v-for="kw in (featured_character.keywords ?? []).slice(0, 3)" :key="kw.id" variant="outline" class="text-[10px]">{{
                                kw.name
                            }}</Badge>
                        </div>
                        <div class="mx-auto mt-4 grid w-full max-w-xs grid-cols-3 gap-2 text-center">
                            <div class="rounded-md bg-muted px-2 py-2">
                                <HeadingEyebrow as="h4">Cost</HeadingEyebrow>
                                <div class="text-base font-bold">{{ featured_character.cost ?? '—' }}</div>
                            </div>
                            <div class="rounded-md bg-muted px-2 py-2">
                                <HeadingEyebrow as="h4">Health</HeadingEyebrow>
                                <div class="text-base font-bold">{{ featured_character.health ?? '—' }}</div>
                            </div>
                            <div class="rounded-md bg-muted px-2 py-2">
                                <HeadingEyebrow as="h4">Defense</HeadingEyebrow>
                                <div class="text-base font-bold">{{ featured_character.defense ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                            <Link
                                :href="
                                    route('characters.view', {
                                        character: featured_character.slug,
                                        miniature: featured_character.standard_miniatures[0].id,
                                        slug: featured_character.standard_miniatures[0].slug,
                                    })
                                "
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

            <!-- ═══ Community Activity ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 200ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <HeadingEyebrow as="h2">From the Community</HeadingEyebrow>
                </div>
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    <!-- Articles -->
                    <div v-if="recent_articles?.length" class="animate-fade-in-up" :style="feedDelays[0]">
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

                    <!-- Site News -->
                    <div v-if="recent_news?.length" class="animate-fade-in-up" :style="feedDelays[1]">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                                <Megaphone class="size-4 text-muted-foreground" />
                                Site News
                            </h3>
                            <Link :href="route('news.index')" class="text-[11px] text-primary hover:underline">All</Link>
                        </div>
                        <Badge class="mb-2 text-[10px] uppercase tracking-wide">Official</Badge>
                        <div class="space-y-1.5">
                            <Link
                                v-for="item in recent_news"
                                :key="item.slug"
                                :href="route('news.view', item.slug)"
                                class="group relative block overflow-hidden rounded-lg border py-2 pl-4 pr-3 transition-all hover:border-primary/30 hover:bg-muted/50"
                            >
                                <span class="absolute inset-y-2 left-1.5 w-0.5 rounded-full bg-primary/50" />
                                <p class="line-clamp-2 break-words text-sm font-medium leading-snug group-hover:text-primary">{{ item.title }}</p>
                                <div class="mt-1 flex items-center gap-1.5 text-[10px] text-muted-foreground">
                                    <span v-if="item.category">{{ item.category }}</span>
                                    <span v-if="item.category">·</span>
                                    <span>{{ item.published_at }}</span>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Transmissions -->
                    <div v-if="recent_transmissions?.length" class="animate-fade-in-up" :style="feedDelays[2]">
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
                    <div v-if="recent_crews?.length" class="animate-fade-in-up" :style="feedDelays[3]">
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
                                <span class="mt-0.5 h-6 w-1 shrink-0 rounded-full" :class="factionBackground(crew.faction)" />
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
        </div>
    </div>
</template>
