<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Lock, Palette, UserRound, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();

const topNav = computed<NavGroup[]>(() => [
    {
        items: [{ title: 'Back to site', href: page.props.currentGameSystem?.home_route ?? route('index'), icon: ArrowLeft }],
    },
]);

const accountGroup: NavGroup = {
    title: 'Account',
    collapsible: true,
    collapsed: false,
    items: [
        { title: 'Profile', href: route('profile.edit'), icon: UserRound },
        { title: 'Password', href: route('password.edit'), icon: Lock },
        { title: 'Appearance', href: route('appearance'), icon: Palette },
    ],
};

const socialGroup: NavGroup = {
    title: 'Social',
    collapsible: true,
    collapsed: false,
    items: [
        { title: 'Friends', href: route('friends.index'), icon: Users, keywords: 'friends friend requests social' },
        { title: 'Notifications', href: route('notifications.index'), icon: Bell },
    ],
};

// Everything else (collection, crews, games, ...) lives in the main navigation and on My Hub, so this
// sidebar only lists account and social settings.
const settingsNav = computed<NavGroup[]>(() => [accountGroup, socialGroup]);
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
