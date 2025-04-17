<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { usePage } from '@inertiajs/vue3';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import {type NavItem, SharedData} from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const mainNavItems: NavItem[] = [
    {
        title: 'Factions',
        items: [
            {
                title: 'Arcanists',
                href: '/faction/arcanists',
                icon: LayoutGrid,
            },{
                title: 'Explorer\'s Society',
                href: '/faction/explorers-society',
                icon: LayoutGrid,
            },
        ]
    }, {
        title: 'Admin',
        items: [
            {
                title: 'Keywords',
                href: route('admin.keywords.index'),
                icon: BookOpen,
            }, {
                title: 'Characteristics',
                href: route('admin.characteristics.index'),
                icon: BookOpen,
            }, {
                title: 'Characters',
                href: route('admin.characters.index'),
                icon: BookOpen,
            },
        ]
    }
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('index')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
