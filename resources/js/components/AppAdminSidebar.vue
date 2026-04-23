<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpCircle,
    BookOpen,
    ExternalLink,
    FileImage,
    Gauge,
    KeyRound,
    MessageSquareText,
    Newspaper,
    Package,
    Puzzle,
    Radio,
    Radius,
    Shield,
    ShieldCheck,
    Swords,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const permissions = computed(() => page.props.auth.permissions ?? []);

const hasPermission = (permission?: string) => {
    if (!permission) return true;
    return permission.split('|').some((p) => permissions.value.includes(p));
};

interface AdminNavGroup {
    title: string;
    items: (NavItem & { permission?: string })[];
}

const adminGroups: AdminNavGroup[] = [
    {
        title: 'Game Data',
        items: [
            { title: 'Characters', href: route('admin.characters.index'), icon: BookOpen, permission: 'view_character' },
            { title: 'Miniatures', href: route('admin.miniatures.index'), icon: BookOpen, permission: 'view_miniature' },
            { title: 'Actions', href: route('admin.actions.index'), icon: Swords, permission: 'view_action' },
            { title: 'Abilities', href: route('admin.abilities.index'), icon: Shield, permission: 'view_ability' },
            { title: 'Triggers', href: route('admin.triggers.index'), icon: Swords, permission: 'view_trigger' },
            { title: 'Keywords', href: route('admin.keywords.index'), icon: KeyRound, permission: 'view_keyword' },
            { title: 'Characteristics', href: route('admin.characteristics.index'), icon: BookOpen, permission: 'view_characteristic' },
            { title: 'Upgrades', href: route('admin.upgrades.index'), icon: ArrowUpCircle, permission: 'view_upgrade' },
            { title: 'Crew Cards', href: route('admin.crews.index'), icon: ArrowUpCircle, permission: 'view_crew' },
            { title: 'Tokens', href: route('admin.tokens.index'), icon: Puzzle, permission: 'view_token' },
            { title: 'Markers', href: route('admin.markers.index'), icon: Radius, permission: 'view_marker' },
            { title: 'Schemes', href: route('admin.schemes.index'), icon: BookOpen, permission: 'view_scheme' },
            { title: 'Strategies', href: route('admin.strategies.index'), icon: BookOpen, permission: 'view_strategy' },
        ],
    },
    {
        title: 'Content',
        items: [
            { title: 'Articles', href: route('admin.blog.posts.index'), icon: Newspaper, permission: 'create_posts|edit_posts' },
            { title: 'Article Categories', href: route('admin.blog.categories.index'), icon: Newspaper, permission: 'create_posts|edit_posts' },
            { title: 'Lore', href: route('admin.lores.index'), icon: BookOpen, permission: 'view_lore' },
            { title: 'Lore Media', href: route('admin.lore_media.index'), icon: BookOpen, permission: 'view_lore' },
            { title: 'Blueprints', href: route('admin.blueprints.index'), icon: FileImage, permission: 'view_blueprint' },
            { title: 'Packages', href: route('admin.packages.index'), icon: Package, permission: 'view_package' },
        ],
    },
    {
        title: 'Community',
        items: [
            { title: 'Channels', href: route('admin.channels.index'), icon: Radio, permission: 'view_channel' },
            { title: 'Transmissions', href: route('admin.transmissions.index'), icon: Radio, permission: 'view_channel' },
            { title: 'POD Links', href: route('admin.pod_links.index'), icon: ExternalLink, permission: 'view_pod_link' },
            { title: 'Feedback', href: route('admin.feedback.index'), icon: MessageSquareText, permission: 'view_feedback' },
        ],
    },
    {
        title: 'Access',
        items: [
            { title: 'Users', href: route('admin.users.index'), icon: Users, permission: 'view_user' },
            { title: 'Roles', href: route('admin.roles.index'), icon: ShieldCheck, permission: 'view_role' },
        ],
    },
];

const dashboardNav = computed<NavItem[]>(() => [
    {
        items: [
            { title: 'Dashboard', href: route('admin.dashboard'), icon: Gauge },
            { title: '← Back to site', href: route('index'), icon: ArrowLeft },
        ],
    },
]);

const filteredAdminNav = computed(() => {
    return adminGroups
        .map((group) => ({
            title: group.title,
            collapsible: true,
            collapsed: false,
            items: group.items.filter((item) => hasPermission(item.permission)),
        }))
        .filter((group) => group.items.length > 0);
});
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
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="dashboardNav" />
            <NavMain :items="filteredAdminNav" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser v-if="page.props.auth.user" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
