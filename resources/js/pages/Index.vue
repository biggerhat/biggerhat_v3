<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { Separator } from '@/components/ui/separator';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { BookOpen, Dice6, FileDown, FileImage, Newspaper, Package, RefreshCw, Search, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const factions = computed(() => page.props.faction_info);

defineProps({
    featured_character: {
        type: Object,
        required: false,
        default: null,
    },
    recent_crews: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
    recent_articles: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
    stats: {
        type: Object,
        required: true,
        default() {
            return {};
        },
    },
});

const open = ref(false);
const commandSearch = ref({});

const commandRoute = (cmdRoute: string) => {
    router.get(cmdRoute);
    open.value = false;
};

function toggleDialog() {
    if (!commandSearch.value.length) {
        axios.get(route('command')).then(function (response) {
            commandSearch.value = response.data;
        });
    }
    open.value = true;
}
</script>

<template>
    <Head title="Home" />
    <div class="relative">
        <!-- Background gradient -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-96 opacity-[0.05] dark:opacity-[0.10]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto flex flex-col gap-10 px-4 pb-12 pt-6 lg:gap-14">
            <!-- ═══ Hero ═══ -->
            <div class="animate-fade-in-up flex flex-col items-center pt-6 lg:pt-10">
                <img src="/images/hat_side.webp" class="h-36 md:h-48" alt="BiggerHat.net" />
                <p class="mt-3 text-center text-muted-foreground">Malifaux Character Database & Tools</p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    <Button variant="outline" class="gap-2" @click="toggleDialog">
                        <Search class="size-4" />
                        <span class="hidden sm:inline">Search characters, keywords, factions...</span>
                        <span class="sm:hidden">Search...</span>
                    </Button>
                </div>
            </div>

            <!-- ═══ Factions ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 80ms">
                <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                    <Link
                        v-for="(faction, key) in factions"
                        :key="key"
                        :href="route('factions.view', key)"
                        class="flex flex-col items-center gap-1.5 rounded-lg p-2 transition-all duration-200 hover:-translate-y-1 hover:scale-105 hover:bg-muted sm:p-3"
                    >
                        <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12 md:size-14" />
                        <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                    </Link>
                </div>
            </div>

            <!-- ═══ Main Content: 2-column on large screens ═══ -->
            <div class="animate-fade-in-up grid gap-10 lg:grid-cols-5 lg:gap-8" style="animation-delay: 160ms">
                <!-- Left column: Crew Builder + Recent Crews -->
                <div class="space-y-8 lg:col-span-3">
                    <!-- Crew Builder CTA -->
                    <Link
                        :href="route('tools.crew_builder.index')"
                        class="group flex items-center gap-4 rounded-xl border-2 border-primary/20 bg-gradient-to-r from-primary/5 to-transparent p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg"
                    >
                        <div
                            class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                        >
                            <Swords class="size-7" />
                        </div>
                        <div class="flex-1">
                            <p class="text-lg font-semibold">Crew Builder</p>
                            <p class="text-sm text-muted-foreground">Build, save, and share your Malifaux crews</p>
                        </div>
                    </Link>

                    <!-- Recent Community Crews -->
                    <div v-if="recent_crews.length">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="font-semibold">Recent Community Crews</h2>
                            <Link :href="route('tools.crew_builder.index')" class="text-xs text-primary hover:underline">View all</Link>
                        </div>
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <Link
                                v-for="crew in recent_crews"
                                :key="crew.id"
                                :href="route('tools.crew_builder.share', crew.share_code)"
                                class="group"
                            >
                                <Card class="h-full transition-all duration-200 group-hover:-translate-y-0.5 group-hover:shadow-md">
                                    <CardContent class="flex items-start gap-3 p-3">
                                        <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium group-hover:text-primary">{{ crew.name }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                                <Badge variant="outline" class="text-[10px]">{{ crew.faction_label }}</Badge>
                                                <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                                <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                            </div>
                                            <div class="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                                                <span>{{ crew.user_name }}</span>
                                                <span>&middot;</span>
                                                <span>{{ crew.created_at }}</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </div>

                    <!-- Recent Articles -->
                    <div v-if="recent_articles.length">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="font-semibold">Latest Articles</h2>
                            <Link :href="route('blog.index')" class="text-xs text-primary hover:underline">View all</Link>
                        </div>
                        <div class="space-y-2">
                            <Link
                                v-for="article in recent_articles"
                                :key="article.slug"
                                :href="route('blog.view', article.slug)"
                                class="group flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-all duration-200 hover:border-primary/30 hover:bg-muted/50"
                            >
                                <Newspaper class="size-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium group-hover:text-primary">{{ article.title }}</p>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <span v-if="article.category">{{ article.category }}</span>
                                        <span v-if="article.category">&middot;</span>
                                        <span>{{ article.published_at }}</span>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Right column: Featured Character + Quick Links -->
                <div class="space-y-8 lg:col-span-2">
                    <!-- Featured Character -->
                    <div v-if="featured_character && featured_character.standard_miniatures?.length">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="font-semibold">Featured Character</h2>
                            <Button variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" @click="router.reload({ only: ['featured_character'] })">
                                <RefreshCw class="size-3" />
                                Shuffle
                            </Button>
                        </div>
                        <div class="flex justify-center">
                            <div class="w-44 md:w-52">
                                <CharacterCardView :miniature="featured_character.standard_miniatures[0]" :character-slug="featured_character.slug" />
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h2 class="mb-3 font-semibold">Explore</h2>
                        <div class="grid grid-cols-2 gap-2">
                            <Link
                                :href="route('keywords.index')"
                                class="group flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <BookOpen class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                                <span class="text-xs font-medium">Keywords</span>
                            </Link>
                            <Link
                                :href="route('packages.index')"
                                class="group flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <Package class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                                <span class="text-xs font-medium">Packages</span>
                            </Link>
                            <Link
                                :href="route('blueprints.index')"
                                class="group flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <FileImage class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                                <span class="text-xs font-medium">Build Instructions</span>
                            </Link>
                            <Link
                                :href="route('tools.pdf.index')"
                                class="group flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <FileDown class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                                <span class="text-xs font-medium">PDF Generator</span>
                            </Link>
                            <Link
                                :href="route('tools.scenario_generator')"
                                class="group flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <Dice6 class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                                <span class="text-xs font-medium">Scenario Generator</span>
                            </Link>
                            <Link
                                :href="route('blog.index')"
                                class="group flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                            >
                                <Newspaper class="size-5 text-muted-foreground transition-colors group-hover:text-primary" />
                                <span class="text-xs font-medium">Articles</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Stats Footer ═══ -->
            <div class="animate-fade-in-up" style="animation-delay: 240ms">
                <Separator class="mb-4" />
                <div class="flex flex-wrap items-center justify-center gap-3 text-xs text-muted-foreground sm:gap-6">
                    <span>{{ stats.characters }} Characters</span>
                    <span>{{ stats.keywords }} Keywords</span>
                    <span>8 Factions</span>
                </div>
            </div>
        </div>
    </div>

    <CommandDialog v-model:open="open">
        <CommandInput placeholder="Search for a topic..." />
        <CommandList>
            <CommandEmpty>No results found.</CommandEmpty>
            <CommandGroup heading="Factions">
                <CommandItem
                    v-for="faction in commandSearch.factions"
                    v-bind:key="faction.name"
                    @select="commandRoute(faction.route)"
                    :value="faction.name"
                >
                    {{ faction.name }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Keywords">
                <CommandItem
                    v-for="keyword in commandSearch.keywords"
                    v-bind:key="keyword.name"
                    @select="commandRoute(keyword.route)"
                    :value="keyword.name"
                >
                    {{ keyword.name }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Characters">
                <CommandItem
                    v-for="character in commandSearch.characters"
                    v-bind:key="character.name"
                    @select="commandRoute(character.route)"
                    :value="character.name"
                >
                    {{ character.name }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Upgrades">
                <CommandItem
                    v-for="upgrade in commandSearch.upgrades"
                    v-bind:key="upgrade.name"
                    @select="commandRoute(upgrade.route)"
                    :value="upgrade.name"
                >
                    {{ upgrade.name }}
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>
</template>
