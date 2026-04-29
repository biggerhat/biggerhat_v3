<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CommandPaletteCatalog from '@/components/CommandPaletteCatalog.vue';
import GameSystemSwitcher from '@/components/GameSystemSwitcher.vue';
import { CommandDialog, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { useMagicKeys, whenever } from '@vueuse/core';
import axios from 'axios';
import {
    BarChart3,
    BookOpen,
    Dice6,
    Hammer,
    Home,
    Library,
    Loader2,
    Newspaper,
    Package,
    Scale,
    Search,
    Shield,
    Sparkles,
    Swords,
    Tags,
    Trophy,
    Users,
    Wand2,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted, ref, type Component } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

interface CommandEntry {
    name: string;
    route: string;
}
interface CommandSearchResults {
    factions?: CommandEntry[];
    keywords?: CommandEntry[];
    characters?: CommandEntry[];
    upgrades?: CommandEntry[];
    miniatures?: CommandEntry[];
    packages?: CommandEntry[];
    tos_allegiances?: CommandEntry[];
    tos_units?: CommandEntry[];
    tos_stratagems?: CommandEntry[];
}

interface QuickAction {
    name: string;
    icon: Component;
    route: string;
    keywords?: string;
}

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);
const isTos = computed(() => page.props.currentGameSystem?.slug === 'tos');

const open = ref(false);
const commandSearch = ref<CommandSearchResults | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);

const goTo = (url: string) => {
    router.get(url);
    open.value = false;
};

/**
 * Static navigation targets surfaced at the top of the palette. These load
 * instantly (no fetch) so the palette is useful even before the dynamic
 * catalog resolves. Kept as plain arrays (not `computed`) because Reka's
 * `Command` re-invokes its default slot in a context where computed-returned
 * objects don't reliably unwrap, surfacing as `.label` / `.items` undefined
 * during mount.
 *
 * Two parallel arrays (one per game system) selected at template level via
 * `v-if="isTos"` instead of one computed array — preserves the literal-array
 * Reka requirement above.
 */
const malifauxNavigateItems: QuickAction[] = [
    // Home hub doubles as the characters/factions landing — no standalone
    // index routes exist for those (only individual view pages + the home grid).
    { name: 'Home / Browse', icon: Home, route: route('index'), keywords: 'home characters factions database browse malifaux' },
    { name: 'Keywords', icon: Tags, route: route('keywords.index'), keywords: 'keywords' },
    { name: 'Character Upgrades', icon: Sparkles, route: route('upgrades.character.index'), keywords: 'upgrades character' },
    { name: 'Crew Upgrades', icon: Sparkles, route: route('upgrades.crew.index'), keywords: 'upgrades crew' },
    { name: 'Crew Builder', icon: Hammer, route: route('tools.crew_builder.index'), keywords: 'crew builder build' },
    { name: 'Advanced Search', icon: Search, route: route('search.view'), keywords: 'advanced search filter' },
    { name: 'Scenario Generator', icon: Wand2, route: route('tools.scenario_generator'), keywords: 'scenario generator random strategy' },
    { name: 'Random Character', icon: Dice6, route: route('characters.random'), keywords: 'random dice surprise' },
];

const tosNavigateItems: QuickAction[] = [
    // No Home entry — the sidebar logo handles routing back to tos.index.
    { name: 'Allegiances', icon: Shield, route: route('tos.allegiances.index'), keywords: 'allegiances factions tos' },
    { name: 'Units', icon: Swords, route: route('tos.units.index'), keywords: 'units tos models' },
    { name: 'Special Rules', icon: BookOpen, route: route('tos.special_rules.index'), keywords: 'special rules tos commander titan fireteam squad' },
    { name: 'Allegiance Cards', icon: BookOpen, route: route('tos.allegiance_cards.index'), keywords: 'allegiance cards tos' },
    { name: 'Assets', icon: Package, route: route('tos.assets.index'), keywords: 'assets tos vehicles gear' },
    { name: 'Stratagems', icon: Newspaper, route: route('tos.stratagems.index'), keywords: 'stratagems tos' },
    { name: 'Abilities', icon: Zap, route: route('tos.abilities.index'), keywords: 'abilities tos' },
    { name: 'Actions', icon: Swords, route: route('tos.actions.index'), keywords: 'actions tos' },
    { name: 'Triggers', icon: Swords, route: route('tos.triggers.index'), keywords: 'triggers tos' },
    { name: 'Advanced Search', icon: Search, route: route('tos.search'), keywords: 'advanced search filter tos' },
    { name: 'Compare Units', icon: Scale, route: route('tos.compare'), keywords: 'compare units tos' },
];

const malifauxMyStuffItems: QuickAction[] = [
    { name: 'My Games', icon: Swords, route: route('games.index'), keywords: 'games tracker my' },
    { name: 'My Tournaments', icon: Trophy, route: route('tournaments.index'), keywords: 'tournaments my' },
    { name: 'My Collection', icon: Library, route: route('collection.index'), keywords: 'collection miniatures owned' },
    { name: 'My Stats', icon: BarChart3, route: route('stats.my'), keywords: 'stats win rate record' },
];

const tosMyStuffItems: QuickAction[] = [
    { name: 'Company Builder', icon: Users, route: route('tos.companies.index'), keywords: 'company builder tos build' },
];

/** Lazy catalog load — kicked off on first idle after mount so first open is warm. */
async function loadCatalog() {
    if (commandSearch.value !== null) return;
    loading.value = true;
    loadError.value = null;
    try {
        const response = await axios.get(route('command'));
        commandSearch.value = response.data ?? {};
    } catch {
        loadError.value = 'Could not load search. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function openDialog() {
    open.value = true;
    await loadCatalog();
}

// Preload during idle so the first palette open is instant.
onMounted(() => {
    const idle = (window as unknown as { requestIdleCallback?: (cb: () => void) => void }).requestIdleCallback;
    if (idle) idle(() => loadCatalog());
    else setTimeout(loadCatalog, 1200);
});

// Global Cmd+K / Ctrl+K shortcut. `useMagicKeys` normalizes across platforms.
const keys = useMagicKeys({
    passive: false,
    onEventFired(e) {
        if ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey) && e.type === 'keydown') {
            e.preventDefault();
        }
    },
});
whenever(keys['Meta+K'], () => openDialog());
whenever(keys['Ctrl+K'], () => openDialog());

const isMac = computed(() => typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.platform));
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 sm:px-6 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <GameSystemSwitcher class="hidden sm:inline-flex" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <!-- Search trigger: a pill with the shortcut hint, standard palette UX. -->
            <button
                type="button"
                aria-label="Open search (⌘K)"
                class="group inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background/60 px-3 text-sm text-muted-foreground transition-colors hover:border-ring hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:min-w-[200px] sm:justify-start"
                @click="openDialog"
            >
                <Search class="size-4 shrink-0" />
                <span class="hidden flex-1 text-left sm:inline">Search database…</span>
                <kbd
                    class="ml-auto hidden h-5 items-center rounded border border-border/60 bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground sm:inline-flex"
                >
                    {{ isMac ? '⌘' : 'Ctrl' }} K
                </kbd>
            </button>
            <button
                type="button"
                aria-label="Open a random character"
                class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                @click="router.get(route('characters.random'))"
            >
                <Dice6 class="size-4" />
            </button>
        </div>
    </header>

    <CommandDialog v-model:open="open">
        <CommandInput placeholder="Search characters, keywords, factions…" />
        <CommandList class="max-h-[65vh]">
            <!-- Quick-action groups render instantly — the user can navigate even
                 before the dynamic catalog has resolved. Static arrays + literal
                 heading strings so Reka's Command default-slot re-evaluation
                 doesn't trip on unwrapped computed refs. -->
            <CommandGroup v-if="isTos" heading="Navigate (The Other Side)">
                <CommandItem
                    v-for="action in tosNavigateItems"
                    :key="action.route"
                    :value="`navigate:${action.name}:${action.keywords ?? ''}`"
                    @select="goTo(action.route)"
                >
                    <component :is="action.icon" class="mr-2 size-4 text-muted-foreground" />
                    {{ action.name }}
                </CommandItem>
            </CommandGroup>
            <CommandGroup v-else heading="Navigate">
                <CommandItem
                    v-for="action in malifauxNavigateItems"
                    :key="action.route"
                    :value="`navigate:${action.name}:${action.keywords ?? ''}`"
                    @select="goTo(action.route)"
                >
                    <component :is="action.icon" class="mr-2 size-4 text-muted-foreground" />
                    {{ action.name }}
                </CommandItem>
            </CommandGroup>

            <CommandSeparator v-if="isLoggedIn" />
            <CommandGroup v-if="isLoggedIn && isTos" heading="My Stuff">
                <CommandItem
                    v-for="action in tosMyStuffItems"
                    :key="action.route"
                    :value="`mine:${action.name}:${action.keywords ?? ''}`"
                    @select="goTo(action.route)"
                >
                    <component :is="action.icon" class="mr-2 size-4 text-muted-foreground" />
                    {{ action.name }}
                </CommandItem>
            </CommandGroup>
            <CommandGroup v-else-if="isLoggedIn" heading="My Stuff">
                <CommandItem
                    v-for="action in malifauxMyStuffItems"
                    :key="action.route"
                    :value="`mine:${action.name}:${action.keywords ?? ''}`"
                    @select="goTo(action.route)"
                >
                    <component :is="action.icon" class="mr-2 size-4 text-muted-foreground" />
                    {{ action.name }}
                </CommandItem>
            </CommandGroup>

            <div v-if="loading" class="flex items-center justify-center gap-2 py-4 text-xs text-muted-foreground">
                <Loader2 class="size-4 animate-spin" /> Loading database…
            </div>
            <div v-else-if="loadError" class="px-4 py-4 text-center text-xs text-destructive">
                {{ loadError }}
                <button type="button" class="ml-2 underline" @click="loadCatalog">Retry</button>
            </div>

            <!-- Dynamic catalog (factions/keywords/characters/etc) only mounts
                 once the user starts typing — keeps the initial open instant. -->
            <CommandPaletteCatalog v-if="!loading && !loadError" :catalog="commandSearch" @select="goTo" />
        </CommandList>
    </CommandDialog>
</template>
