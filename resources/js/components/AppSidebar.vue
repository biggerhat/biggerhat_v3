<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavSuperAdmin from "@/components/ui/NavSuperAdmin.vue";
import NavUser from '@/components/NavUser.vue';
import {Link, usePage} from '@inertiajs/vue3';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem
} from '@/components/ui/sidebar';
import {type NavItem, SharedData} from '@/types';
import {BookOpen, Bot, CircleDollarSign, Dice6, KeyRound, Puzzle, Radius, TextSearch, FileText} from 'lucide-vue-next';
import ArcanistLogo from "@/components/ArcanistLogo.vue";
import AppLogo from './AppLogo.vue';
import ExplorersLogo from "@/components/ExplorersLogo.vue";
import BayouLogo from "@/components/BayouLogo.vue";
import GuildLogo from "@/components/GuildLogo.vue";
import NeverbornLogo from "@/components/NeverbornLogo.vue";
import OutcastsLogo from "@/components/OutcastsLogo.vue";
import ResurrectionistsLogo from "@/components/ResurrectionistsLogo.vue";
import TenThundersLogo from "@/components/TenThundersLogo.vue";
import Button from "@/components/ui/button/Button.vue";

const page = usePage<SharedData>();

const mainNavItems: NavItem[] = [
    {
        items: [
            {
                title: 'Advanced Search',
                href: '/advanced',
                icon: TextSearch,
            }, {
                title: 'Random Character',
                href: route('characters.random'),
                icon: Dice6,
            },
        ],
    }, {
        title: 'Factions',
        collapsible: true,
        collapsed: false,
        items: [
            {
                title: 'Arcanists',
                href: route('factions.view', 'arcanists'),
                icon: ArcanistLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Bayou',
                href: route('factions.view', 'bayou'),
                icon: BayouLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Explorer\'s Society',
                href: route('factions.view', 'explorers_society'),
                icon: ExplorersLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Guild',
                href: route('factions.view', 'guild'),
                icon: GuildLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Neverborn',
                href: route('factions.view', 'neverborn'),
                icon: NeverbornLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Outcasts',
                href: route('factions.view', 'outcasts'),
                icon: OutcastsLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Resurrectionists',
                href: route('factions.view', 'resurrectionists'),
                icon: ResurrectionistsLogo,
                icon_class: 'w-8 h-8',
            }, {
                title: 'Ten Thunders',
                href: route('factions.view', 'ten_thunders'),
                icon: TenThundersLogo,
                icon_class: 'w-8 h-8',
            }
        ]
    }, {
        title: 'References',
        collapsible: true,
        collapsed: true,
        items: [
            {
                title: 'Keywords',
                href: route('keywords.index'),
                icon: KeyRound,
            }, {
                title: 'Markers',
                href: route('markers.index'),
                icon: Radius,
            }, {
                title: 'Tokens',
                href: route('tokens.index'),
                icon: Puzzle,
            }
        ]
    }, {
        title: 'Tools',
        collapsible: true,
        collapsed: true,
        items: [
            // {
            //     title: 'Hat Gamin Discord Bot',
            //     href: route('tools.hat_gamin'),
            //     icon: Bot,
            // },
            {
                title: 'PDF Generator',
                href: route('tools.pdf.index'),
                icon: FileText,
            }
        ]
    }
];

const superAdminNavItems: NavItem[] = [
    {
        title: 'Admin',
        collapsible: true,
        collapsed: true,
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
            }, {
                title: 'Character Upgrades',
                href: route('admin.upgrades.index'),
                icon: BookOpen,
            }, {
                title: 'Crew Upgrades',
                href: route('admin.crews.index'),
                icon: BookOpen,
            }, {
                title: 'Tokens',
                href: route('admin.tokens.index'),
                icon: BookOpen,
            }, {
                title: 'Markers',
                href: route('admin.markers.index'),
                icon: BookOpen,
            }, {
                title: 'Schemes',
                href: route('admin.schemes.index'),
                icon: BookOpen,
            }, {
                title: 'Strategies',
                href: route('admin.strategies.index'),
                icon: BookOpen,
            },
        ]
    }
];

const download = () => {
    window.open(route('tools.pdf_generate'), '_blank').focus();
};

const footerNavItems: NavItem[] = [
    {
        title: 'Donate on Ko-fi',
        href: 'https://ko-fi.com/biggerhat',
        icon: CircleDollarSign,
    },
];
</script>

<template>
    <Sidebar collapsible="offcanvas" variant="sidebar">
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
