<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bot,
    BookOpen,
    Calendar,
    CircleDollarSign,
    Dice6,
    FileImage,
    Heart,
    KeyRound,
    Library,
    Newspaper,
    Package,
    Puzzle,
    Radio,
    Radius,
    Scale,
    Shield,
    ShieldCheck,
    Swords,
    TextSearch,
    ArrowUpCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const canAccessAdmin = computed(() => !!page.props.auth.can_access_admin);

const channelIds = computed(() => page.props.auth.channel_ids ?? []);

const myHatNavItems = computed(() => {
    if (!isAuthenticated.value) return [];
    const items = [
        {
            title: 'My Collection',
            href: route('collection.index'),
            icon: Library,
        },
        {
            title: 'My Wishlists',
            href: route('wishlists.index'),
            icon: Heart,
        },
    ];

    if (channelIds.value.length > 0) {
        items.push({
            title: 'My Channels',
            href: route('channels.my'),
            icon: Radio,
        });
    }

    return [
        {
            title: 'My Hat',
            collapsible: true,
            collapsed: false,
            items,
        },
    ];
});

const mainNavItems = computed<NavItem[]>(() => [
    {
        items: [
            {
                title: 'Advanced Search',
                href: route('search.view'),
                icon: TextSearch,
            },
            {
                title: 'Crew Builder',
                href: route('tools.crew_builder.index'),
                icon: Swords,
            },
            {
                title: 'Game Tracker',
                href: route('games.index'),
                icon: Swords,
                badge: 'Beta',
            },
            {
                title: 'Articles',
                href: route('blog.index'),
                icon: Newspaper,
            },
            {
                title: 'Across the Aethervox',
                href: route('channels.index'),
                icon: Radio,
            },
            ...(canAccessAdmin.value
                ? [
                      {
                          title: 'Admin',
                          href: route('admin.dashboard'),
                          icon: ShieldCheck,
                      },
                  ]
                : []),
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
        title: 'Tools',
        collapsible: true,
        collapsed: false,
        items: [
            {
                title: 'Compare',
                href: route('tools.compare'),
                icon: Scale,
            },
            {
                title: 'Scenario Generator',
                href: route('tools.scenario_generator'),
                icon: Dice6,
            },
            {
                title: 'Hat Gamin Bot',
                href: route('tools.hat_gamin'),
                icon: Bot,
            },
        ],
    },
    {
        title: 'References',
        collapsible: true,
        collapsed: false,
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
                title: 'Triggers',
                href: route('triggers.index'),
                icon: Swords,
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
                title: 'Crew Cards',
                href: route('upgrades.crew.index'),
                icon: ArrowUpCircle,
            },
            {
                title: 'Upgrades',
                href: route('upgrades.character.index'),
                icon: ArrowUpCircle,
            },
            {
                title: 'Packages',
                href: route('packages.index'),
                icon: Package,
            },
            {
                title: 'Lore',
                href: route('lores.index'),
                icon: BookOpen,
            },
            {
                title: 'Build Instructions',
                href: route('blueprints.index'),
                icon: FileImage,
            },
            {
                title: 'Gaining Grounds',
                href: route('seasons.index'),
                icon: Calendar,
            },
        ],
    },
]);

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
            <NavMain v-if="myHatNavItems.length > 0" :items="myHatNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
