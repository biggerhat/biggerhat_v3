<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CommandPaletteCatalog from '@/components/CommandPaletteCatalog.vue';
import GameSystemSwitcher from '@/components/GameSystemSwitcher.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { CommandDialog, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { buildMainNav, buildMyHatNav, buildTosMyStuff, buildTosNav } from '@/lib/navData';
import { flattenNavGroups } from '@/lib/navFlatten';
import type { BreadcrumbItemType, NavItem, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { useMagicKeys, whenever } from '@vueuse/core';
import axios from 'axios';
import { Dice6, Loader2, Search } from 'lucide-vue-next';
import { computed, markRaw, onMounted, ref, type Component } from 'vue';

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
const canAccessAdmin = computed(() => !!page.props.auth.can_access_admin);
const campaignFeaturesEnabled = computed(() => !!page.props.campaign_features_enabled);
const channelIds = computed(() => page.props.auth.channel_ids ?? []);

const open = ref(false);
const commandSearch = ref<CommandSearchResults | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);

const goTo = (url: string) => {
    router.get(url);
    open.value = false;
};

function toQuickAction(item: NavItem): QuickAction {
    return { name: item.title, icon: item.icon as Component, route: item.href, keywords: item.keywords };
}

// 'characters.random' (true single-click random) has no sidebar equivalent —
// the sidebar's similarly-named entry actually points at the filtered picker
// (see lib/navData.ts's 'Random Character Picker'). The one palette-only
// extra alongside the shared nav data below.
const EXTRA_MALIFAUX_ITEMS: NavItem[] = [
    { title: 'Random Character', href: route('characters.random'), icon: Dice6, keywords: 'random dice surprise' },
];

/**
 * Derived from the same nav-tree builders AppSidebar.vue uses (lib/navData.ts
 * + lib/navFlatten.ts), then `markRaw`'d per entry. `markRaw` keeps each
 * object a plain, non-proxied value even though the source computed is
 * reactive — preserving the "plain object" contract the previous hand-
 * written literal arrays relied on for Reka's `Command`, which re-invokes
 * its default slot in a context where computed-returned/reactive objects
 * don't reliably unwrap (surfacing as `.label`/`.items` undefined during
 * mount).
 *
 * Faction/allegiance browse links are intentionally omitted here (empty
 * `factionItems`/`allegianceItems`) since the dynamic catalog below already
 * covers per-faction/per-allegiance search.
 */
const malifauxMyStuffItems = computed(() =>
    flattenNavGroups(
        buildMyHatNav({
            isAuthenticated: isLoggedIn.value,
            hasChannels: channelIds.value.length > 0,
        }),
    ).map((item) => markRaw(toQuickAction(item))),
);

// buildMainNav() now embeds a "My Hat" group internally (single source of
// truth for the sidebar's group order), so its flattened output would
// otherwise duplicate every item malifauxMyStuffItems already lists under
// "My Stuff" — filter those out by href rather than special-casing the
// group name.
const malifauxNavigateItems = computed(() => {
    const myStuffHrefs = new Set(malifauxMyStuffItems.value.map((item) => item.route));
    return [
        ...flattenNavGroups(
            buildMainNav({
                isAuthenticated: isLoggedIn.value,
                canAccessAdmin: canAccessAdmin.value,
                campaignFeaturesEnabled: campaignFeaturesEnabled.value,
                hasChannels: channelIds.value.length > 0,
                factionItems: [],
            }),
        ).filter((item) => !myStuffHrefs.has(item.href)),
        ...EXTRA_MALIFAUX_ITEMS,
    ].map((item) => markRaw(toQuickAction(item)));
});

const tosMyStuffItems = computed(() => buildTosMyStuff({ isAuthenticated: isLoggedIn.value }).map((item) => markRaw(toQuickAction(item))));

// buildTosNav() now embeds a "My Hat" group internally too — same
// de-duplication as malifauxNavigateItems above.
const tosNavigateItems = computed(() => {
    const myStuffHrefs = new Set(tosMyStuffItems.value.map((item) => item.route));
    return flattenNavGroups(
        buildTosNav({
            isAuthenticated: isLoggedIn.value,
            canAccessAdmin: canAccessAdmin.value,
            allegianceItems: [],
        }),
    )
        .filter((item) => !myStuffHrefs.has(item.href))
        .map((item) => markRaw(toQuickAction(item)));
});

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
            <GameSystemSwitcher compact class="sm:hidden" />
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
            <NotificationBell v-if="page.props.auth.user" />
        </div>
    </header>

    <CommandDialog v-model:open="open">
        <CommandInput placeholder="Search characters, keywords, factions…" />
        <CommandList class="max-h-[65vh]">
            <!-- Quick-action groups render instantly — the user can navigate even
                 before the dynamic catalog has resolved. Entries are markRaw'd
                 (see the computed refs above) and headings are literal strings
                 so Reka's Command default-slot re-evaluation doesn't trip on
                 unwrapped reactive objects. -->
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
