<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    Bell,
    Building2,
    CalendarDays,
    Dice6,
    FileImage,
    Gauge,
    Heart,
    Library,
    Lock,
    Package,
    Palette,
    Shield,
    Swords,
    TextSearch,
    Trophy,
    UserRound,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();

const topNav = computed<NavGroup[]>(() => [
    {
        items: [{ title: '← Back to site', href: page.props.currentGameSystem?.home_route ?? route('index'), icon: ArrowLeft }],
    },
]);

const accountGroup: NavGroup = {
    title: 'Account',
    collapsible: true,
    collapsed: false,
    items: [
        { title: 'Overview', href: route('overview'), icon: Gauge },
        { title: 'Profile', href: route('profile.edit'), icon: UserRound },
        { title: 'Password', href: route('password.edit'), icon: Lock },
        { title: 'Appearance', href: route('appearance'), icon: Palette },
        { title: 'Friends', href: route('friends.index'), icon: Users, keywords: 'friends friend requests social' },
        { title: 'Notifications', href: route('notifications.index'), icon: Bell },
    ],
};

// Cross-system items — Wishlists can hold items from either game, and My
// Stats is framed as "yours" rather than tied to one system's content.
const generalGroup: NavGroup = {
    title: 'General',
    collapsible: true,
    collapsed: false,
    items: [
        { title: 'Wishlists', href: route('wishlists.index'), icon: Heart },
        { title: 'My Stats', href: route('stats.my'), icon: BarChart3 },
    ],
};

const malifauxGroup: NavGroup = {
    title: 'Malifaux',
    collapsible: true,
    collapsed: false,
    items: [
        { title: 'Collection', href: route('collection.index'), icon: Library },
        { title: 'Crew Builds', href: route('tools.crew_builder.index'), icon: Swords },
        { title: 'Custom Cards', href: route('tools.card_creator.index'), icon: FileImage },
        { title: 'Campaigns', href: route('campaigns.index'), icon: CalendarDays },
        { title: 'Games', href: route('games.index'), icon: Dice6 },
        { title: 'Tournaments', href: route('tournaments.index'), icon: Trophy },
        { title: 'Saved Searches', href: route('search.view'), icon: TextSearch },
    ],
};

const tosGroup: NavGroup = {
    title: 'The Other Side',
    collapsible: true,
    collapsed: false,
    items: [
        { title: 'Collection', href: route('tos.collection.index'), icon: Package },
        { title: 'Companies', href: route('tos.companies.index'), icon: Building2 },
        { title: 'Garrisons', href: route('tos.garrisons.index'), icon: Shield },
        { title: 'Saved Searches', href: route('tos.search'), icon: TextSearch },
    ],
};

const settingsNav = computed<NavGroup[]>(() => [accountGroup, generalGroup, malifauxGroup, tosGroup]);
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('overview')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="topNav" />
            <NavMain :items="settingsNav" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
