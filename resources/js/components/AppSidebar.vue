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
    Calendar,
    Bot,
    CircleDollarSign,
    Dice6,
    FileImage,
    ExternalLink,
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
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();
const permissions = computed(() => page.props.auth.permissions ?? []);
const isAuthenticated = computed(() => !!page.props.auth.user);

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

const mainNavItems: NavItem[] = [
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
        title: 'Upgrades',
        href: route('admin.upgrades.index'),
        icon: BookOpen,
        permission: 'view_upgrade',
    },
    {
        title: 'Crew Cards',
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
        title: 'Packages',
        href: route('admin.packages.index'),
        icon: Package,
        permission: 'view_package',
    },
    {
        title: 'Lore',
        href: route('admin.lores.index'),
        icon: BookOpen,
        permission: 'view_lore',
    },
    {
        title: 'Lore Media',
        href: route('admin.lore_media.index'),
        icon: BookOpen,
        permission: 'view_lore',
    },
    {
        title: 'Blueprints',
        href: route('admin.blueprints.index'),
        icon: FileImage,
        permission: 'view_blueprint',
    },
    {
        title: 'Channels',
        href: route('admin.channels.index'),
        icon: Radio,
        permission: 'view_channel',
    },
    {
        title: 'Transmissions',
        href: route('admin.transmissions.index'),
        icon: Radio,
        permission: 'view_channel',
    },
    {
        title: 'POD Links',
        href: route('admin.pod_links.index'),
        icon: ExternalLink,
        permission: 'view_pod_link',
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
            <NavMain v-if="myHatNavItems.length > 0" :items="myHatNavItems" />
            <NavSuperAdmin v-if="filteredAdminNavItems.length > 0" :items="filteredAdminNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
