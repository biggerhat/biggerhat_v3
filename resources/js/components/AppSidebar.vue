<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { buildMainNav, buildTosNav } from '@/lib/navData';
import { type NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useSwipe } from '@vueuse/core';
import { computed, onMounted } from 'vue';
import AppLogo from './AppLogo.vue';
import GameSystemSwitcher from './GameSystemSwitcher.vue';

const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const canAccessAdmin = computed(() => !!page.props.auth.can_access_admin);

const channelIds = computed(() => page.props.auth.channel_ids ?? []);

const isTos = computed(() => page.props.currentGameSystem?.slug === 'tos');
const campaignFeaturesEnabled = computed(() => !!page.props.campaign_features_enabled);

// Allegiance entries from the shared `tos_allegiance_info` map — same source
// of truth as the AllegianceLogo component, so admin-uploaded logos and
// admin-created allegiances flow through automatically. Sorted main-then-
// syndicate then alphabetically, matching the Allegiances index ordering.
const tosAllegianceItems = computed(() => {
    const info = page.props.tos_allegiance_info ?? {};
    return Object.values(info)
        .slice()
        .sort((a, b) => {
            const synd = Number(a.is_syndicate ?? false) - Number(b.is_syndicate ?? false);
            if (synd !== 0) return synd;
            return String(a.name).localeCompare(String(b.name));
        })
        .map((a) => ({
            title: a.name as string,
            href: route('tos.allegiances.view', a.slug as string),
            icon: AllegianceLogo,
            icon_class: 'w-8 h-8',
            icon_props: { allegiance: a.slug as string },
        }));
});

// Faction entries from the shared `faction_info` map — mirrors the TOS
// allegiance pattern above so new factions (rare) don't need a sidebar edit.
// Alphabetical to match the prior hardcoded order.
const malifauxFactionItems = computed(() => {
    const info = page.props.faction_info ?? {};
    return Object.values(info)
        .slice()
        .sort((a, b) => String(a.name).localeCompare(String(b.name)))
        .map((f) => ({
            title: f.name as string,
            href: route('factions.view', f.slug as string),
            icon: FactionLogo,
            icon_class: 'w-8 h-8',
            icon_props: { faction: f.slug as string },
        }));
});

const tosNavItems = computed(() =>
    buildTosNav({
        isAuthenticated: isAuthenticated.value,
        canAccessAdmin: canAccessAdmin.value,
        allegianceItems: tosAllegianceItems.value,
    }),
);

const mainNavItems = computed(() =>
    buildMainNav({
        isAuthenticated: isAuthenticated.value,
        canAccessAdmin: canAccessAdmin.value,
        campaignFeaturesEnabled: campaignFeaturesEnabled.value,
        hasChannels: channelIds.value.length > 0,
        factionItems: malifauxFactionItems.value,
    }),
);

// Donate on Ko-fi lives in the page footer (AppLayout.vue) only, to avoid a
// duplicate link — the sidebar footer still renders NavFooter for the
// guest-only Login item below.
const footerNavItems: NavItem[] = [];

const { isMobile, openMobile, setOpenMobile } = useSidebar();

// Swipe-right-from-the-edge opens the mobile sidebar. Restricted to swipes
// starting within EDGE_ZONE_PX of the left edge so it doesn't hijack normal
// horizontal scrolling elsewhere on the page (wide tables, card carousels).
// Deliberately generous (not a razor-thin hitbox): real thumbs rarely start
// a swipe within a couple pixels of the true bezel, and many phones/browsers
// already reserve a slim strip at the true edge for their own back-gesture,
// so a narrow zone can end up entirely inside territory a real device never
// even reports touchstart for.
const EDGE_ZONE_PX = 56;

onMounted(() => {
    const { coordsStart, direction } = useSwipe(window, {
        onSwipeEnd() {
            if (!isMobile.value || openMobile.value) return;
            if (direction.value === 'right' && coordsStart.x <= EDGE_ZONE_PX) {
                setOpenMobile(true);
            }
        },
    });
});
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <!-- Header tint = subtle visual cue for which game system is active.
             TOS gets a slate gradient, Malifaux gets a warm card-color band.
             Beyond the GameSystemSwitcher pill, this is the only chrome that
             changes between systems — keeps users oriented after a switch. -->
        <SidebarHeader
            :class="
                isTos
                    ? 'bg-gradient-to-b from-slate-200/60 to-transparent dark:from-slate-800/40'
                    : 'bg-gradient-to-b from-amber-100/40 to-transparent dark:from-amber-900/20'
            "
        >
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <!-- Logo routes to the active game system's home so TOS users
                             stay in TOS when they tap it. Falls back to the Malifaux
                             index if shared data is somehow missing. -->
                        <Link :href="page.props.currentGameSystem?.home_route ?? route('index')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div class="px-2 pb-1 sm:hidden">
                <GameSystemSwitcher />
            </div>
            <div class="px-2 pb-1 text-[10px] uppercase tracking-wider text-muted-foreground">
                {{ isTos ? 'The Other Side' : 'Malifaux' }}
            </div>
        </SidebarHeader>

        <SidebarContent>
            <template v-if="isTos">
                <NavMain :items="tosNavItems" />
            </template>
            <template v-else>
                <NavMain :items="mainNavItems" />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
