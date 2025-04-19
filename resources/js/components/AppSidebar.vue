<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavSuperAdmin from "@/components/ui/NavSuperAdmin.vue";
import NavUser from '@/components/NavUser.vue';
import { usePage } from '@inertiajs/vue3';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import {type NavItem, SharedData} from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, TextSearch, CircleDollarSign } from 'lucide-vue-next';
import ArcanistLogo from "@/components/ArcanistLogo.vue";
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const mainNavItems: NavItem[] = [
    {
        items: [
            {
                title: 'Advanced Search',
                href: '/advanced',
                icon: TextSearch,
            }
        ],
    }, {
        title: 'Factions',
        items: [
            {
                title: 'Arcanists',
                href: '/faction/arcanists',
                icon: ArcanistLogo,
                icon_class: 'w-10 h-10',
            },{
                title: 'Explorer\'s Society',
                href: '/faction/explorers-society',
                icon: LayoutGrid,
            },
        ]
    }
];

const superAdminNavItems: NavItem[] = [
    {
        title: 'Admin',
        items: [
            {
                title: 'Characters',
                href: route('admin.characters.index'),
                icon: BookOpen,
            }, {
                title: 'Miniatures',
                href: route('admin.miniatures.index'),
                icon: BookOpen,
            }, {
                title: 'Actions',
                href: route('admin.actions.index'),
                icon: BookOpen,
            }, {
                title: 'Triggers',
                href: route('admin.triggers.index'),
                icon: BookOpen,
            }, {
                title: 'Abilities',
                href: route('admin.abilities.index'),
                icon: BookOpen,
            }, {
                title: 'Keywords',
                href: route('admin.keywords.index'),
                icon: BookOpen,
            }, {
                title: 'Characteristics',
                href: route('admin.characteristics.index'),
                icon: BookOpen,
            },
        ]
    }
];

const footerNavItems: NavItem[] = [
    {
        title: 'Donate on Ko-fi',
        href: 'https://ko-fi.com/biggerhat',
        icon: CircleDollarSign,
    },
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
            <NavSuperAdmin v-if="page.props.auth.is_super_admin" :items="superAdminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
