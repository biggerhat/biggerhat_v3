<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import GameSystemSwitcher from '@/components/GameSystemSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { adminNavIcon } from '@/lib/adminNavIcons';
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Gauge } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();

// Groups arrive already permission-filtered from App\Support\AdminNav (shared
// Inertia prop) — the same source the dashboard tiles and Cmd-K palette use.
const adminNav = computed<NavGroup[]>(() =>
    (page.props.adminNav ?? []).map((group) => ({
        title: group.title,
        collapsible: true,
        collapsed: false,
        items: group.items.map((item) => ({ title: item.title, href: item.href, icon: adminNavIcon(item.icon), external: item.external })),
    })),
);

const dashboardNav = computed<NavGroup[]>(() => [
    {
        items: [
            { title: 'Dashboard', href: route('admin.dashboard'), icon: Gauge },
            // Return to whichever game system the user is currently on (TOS vs Malifaux).
            // home_route is a resolved URL, not a route name.
            { title: '← Back to site', href: page.props.currentGameSystem?.home_route ?? route('index'), icon: ArrowLeft },
        ],
    },
]);
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('admin.dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div class="px-2 pb-1 sm:hidden">
                <GameSystemSwitcher />
            </div>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="dashboardNav" />
            <NavMain :items="adminNav" storage-key="admin" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
