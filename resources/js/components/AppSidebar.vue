<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import NavSuperAdmin from '@/components/ui/NavSuperAdmin.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpCircle,
    BookOpen,
    CircleDollarSign,
    Dice6,
    FileText,
    KeyRound,
    Newspaper,
    Puzzle,
    Radius,
    Shield,
    ShieldCheck,
    Swords,
    TextSearch,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const permissions = computed(() => page.props.auth.permissions ?? []);

const mainNavItems: NavItem[] = [
    {
        items: [
            {
                title: 'Advanced Search',
                href: route('search.view'),
                icon: TextSearch,
            },
            {
                title: 'Random Character',
                href: route('characters.random'),
                icon: Dice6,
            },
            {
                title: 'Articles',
                href: route('blog.index'),
                icon: Newspaper,
            },
        ],
    },
    {
        title: 'Factions',
        collapsible: true,
        collapsed: false,
        items: [
            {
                title: 'Arcanists',
                href: route('factions.view', 'arcanists'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'arcanists' },
            },
            {
                title: 'Bayou',
                href: route('factions.view', 'bayou'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'bayou' },
            },
            {
                title: "Explorer's Society",
                href: route('factions.view', 'explorers_society'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'explorers_society' },
            },
            {
                title: 'Guild',
                href: route('factions.view', 'guild'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'guild' },
            },
            {
                title: 'Neverborn',
                href: route('factions.view', 'neverborn'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'neverborn' },
            },
            {
                title: 'Outcasts',
                href: route('factions.view', 'outcasts'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'outcasts' },
            },
            {
                title: 'Resurrectionists',
                href: route('factions.view', 'resurrectionists'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'resurrectionists' },
            },
            {
                title: 'Ten Thunders',
                href: route('factions.view', 'ten_thunders'),
                icon: FactionLogo,
                icon_class: 'w-8 h-8',
                icon_props: { faction: 'ten_thunders' },
            },
        ],
    },
    {
        title: 'References',
        collapsible: true,
        collapsed: true,
        items: [
            {
                title: 'Actions',
                href: route('actions.index'),
                icon: Swords,
            },
            {
                title: 'Abilities',
                href: route('abilities.index'),
                icon: Shield,
            },
            {
                title: 'Keywords',
                href: route('keywords.index'),
                icon: KeyRound,
            },
            {
                title: 'Markers',
                href: route('markers.index'),
                icon: Radius,
            },
            {
                title: 'Tokens',
                href: route('tokens.index'),
                icon: Puzzle,
            },
            {
                title: 'Crew Upgrades',
                href: route('upgrades.crew.index'),
                icon: ArrowUpCircle,
            },
            {
                title: 'Character Upgrades',
                href: route('upgrades.character.index'),
                icon: ArrowUpCircle,
            },
        ],
    },
    {
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
            },
        ],
    },
];

const allAdminNavItems: NavItem[] = [
    {
        title: 'Characters',
        href: route('admin.characters.index'),
        icon: BookOpen,
        permission: 'view_character',
    },
    {
        title: 'Miniatures',
        href: route('admin.miniatures.index'),
        icon: BookOpen,
        permission: 'view_miniature',
    },
    {
        title: 'Actions',
        href: route('admin.actions.index'),
        icon: BookOpen,
        permission: 'view_action',
    },
    {
        title: 'Triggers',
        href: route('admin.triggers.index'),
        icon: BookOpen,
        permission: 'view_trigger',
    },
    {
        title: 'Abilities',
        href: route('admin.abilities.index'),
        icon: BookOpen,
        permission: 'view_ability',
    },
    {
        title: 'Keywords',
        href: route('admin.keywords.index'),
        icon: BookOpen,
        permission: 'view_keyword',
    },
    {
        title: 'Characteristics',
        href: route('admin.characteristics.index'),
        icon: BookOpen,
        permission: 'view_characteristic',
    },
    {
        title: 'Character Upgrades',
        href: route('admin.upgrades.index'),
        icon: BookOpen,
        permission: 'view_upgrade',
    },
    {
        title: 'Crew Upgrades',
        href: route('admin.crews.index'),
        icon: BookOpen,
        permission: 'view_crew',
    },
    {
        title: 'Tokens',
        href: route('admin.tokens.index'),
        icon: BookOpen,
        permission: 'view_token',
    },
    {
        title: 'Markers',
        href: route('admin.markers.index'),
        icon: BookOpen,
        permission: 'view_marker',
    },
    {
        title: 'Schemes',
        href: route('admin.schemes.index'),
        icon: BookOpen,
        permission: 'view_scheme',
    },
    {
        title: 'Strategies',
        href: route('admin.strategies.index'),
        icon: BookOpen,
        permission: 'view_strategy',
    },
    {
        title: 'Articles',
        href: route('admin.blog.posts.index'),
        icon: Newspaper,
        permission: 'create_posts|edit_posts',
    },
    {
        title: 'Article Categories',
        href: route('admin.blog.categories.index'),
        icon: Newspaper,
        permission: 'create_posts|edit_posts',
    },
    {
        title: 'Users',
        href: route('admin.users.index'),
        icon: Users,
        permission: 'view_user',
    },
    {
        title: 'Roles',
        href: route('admin.roles.index'),
        icon: ShieldCheck,
        permission: 'view_role',
    },
];

const hasPermission = (permission: string) => permission.split('|').some((p) => permissions.value.includes(p));

const filteredAdminNavItems = computed(() => {
    const visibleItems = allAdminNavItems.filter((item) => item.permission && hasPermission(item.permission));
    if (visibleItems.length === 0) return [];
    return [
        {
            title: 'Admin',
            collapsible: true,
            collapsed: true,
            items: visibleItems,
        },
    ];
});

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
            <NavSuperAdmin v-if="filteredAdminNavItems.length > 0" :items="filteredAdminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
