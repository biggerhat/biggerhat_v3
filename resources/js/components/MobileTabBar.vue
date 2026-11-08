<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { openCommandPalette } from '@/composables/useCommandPalette';
import { matchScore } from '@/lib/navActive';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Hammer, Library, Menu, Play, Search } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

/**
 * Phone-only bottom navigation for the four things people reach for most, plus the full sidebar.
 * Thumb-reachable, labelled, and ≥ 44px tall. Pages with their own full-screen controls (the game
 * tracker, the crew editor) opt out; other pages' fixed bottom bars sit above it via `--tabbar-h`.
 */
interface Tab {
    key: string;
    label: string;
    icon: Component;
    href?: string;
    action?: () => void;
}

const page = usePage<SharedData>();
const { setOpenMobile } = useSidebar();

const HIDDEN_ON = new Set(['Games/Show', 'Tools/CrewBuilder/Index']);
const visible = computed(() => !HIDDEN_ON.has(page.component));

const isTos = computed(() => page.props.currentGameSystem?.slug === 'tos');
const isLoggedIn = computed(() => !!page.props.auth.user);

const tabs = computed<Tab[]>(() => {
    const browse: Tab = { key: 'browse', label: 'Browse', icon: Library, href: isTos.value ? route('tos.index') : route('index') };
    const search: Tab = { key: 'search', label: 'Search', icon: Search, action: openCommandPalette };
    const more: Tab = { key: 'more', label: 'Menu', icon: Menu, action: () => setOpenMobile(true) };

    return isTos.value
        ? [
              browse,
              { key: 'build', label: 'Build', icon: Hammer, href: route('tos.companies.index') },
              { key: 'units', label: 'Units', icon: Play, href: route('tos.units.index') },
              search,
              more,
          ]
        : [
              browse,
              { key: 'build', label: 'Build', icon: Hammer, href: route('tools.crew_builder.index') },
              { key: 'play', label: 'Play', icon: Play, href: isLoggedIn.value ? route('games.index') : route('login') },
              search,
              more,
          ];
});

// A tab is active when it is the best match for the current URL; Browse is the catch-all for everything else.
const activeKey = computed(() => {
    let best: { key: string; score: number } | null = null;
    for (const tab of tabs.value) {
        if (!tab.href || tab.key === 'browse') continue;
        const score = matchScore(tab.href, page.url);
        if (score >= 0 && (!best || score > best.score)) best = { key: tab.key, score };
    }

    return best?.key ?? 'browse';
});

const itemClass = (key: string) => [
    'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium transition-colors',
    activeKey.value === key ? 'text-primary' : 'text-muted-foreground hover:text-foreground',
];
</script>

<template>
    <nav
        v-if="visible"
        data-tabbar
        aria-label="Primary"
        class="fixed inset-x-0 bottom-0 z-40 flex border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-sm md:hidden print:hidden"
    >
        <template v-for="tab in tabs" :key="tab.key">
            <Link v-if="tab.href" :href="tab.href" :class="itemClass(tab.key)" :aria-current="activeKey === tab.key ? 'page' : undefined">
                <component :is="tab.icon" class="size-5" aria-hidden="true" />
                {{ tab.label }}
            </Link>
            <button v-else type="button" :class="itemClass(tab.key)" @click="tab.action?.()">
                <component :is="tab.icon" class="size-5" aria-hidden="true" />
                {{ tab.label }}
            </button>
        </template>
    </nav>
</template>
