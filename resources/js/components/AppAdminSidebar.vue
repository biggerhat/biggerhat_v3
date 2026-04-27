<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import GameSystemSwitcher from '@/components/GameSystemSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    ArrowUpCircle,
    BookOpen,
    Clock,
    Eraser,
    ExternalLink,
    FileImage,
    FileText,
    Flag,
    Gauge,
    ImageOff,
    Key,
    KeyRound,
    Megaphone,
    MessageSquareText,
    MonitorSmartphone,
    Newspaper,
    Package,
    Puzzle,
    Radio,
    Radius,
    ServerCrash,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Swords,
    Telescope as TelescopeIcon,
    Trash2,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const permissions = computed(() => page.props.auth.permissions ?? []);
const isSuperAdmin = computed(() => !!page.props.auth.is_super_admin);

const hasPermission = (permission?: string) => {
    if (!permission) return true;
    if (permission === 'super_admin') return isSuperAdmin.value;
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
    {
        title: 'TOS — Units',
        items: [
            { title: 'Units', href: route('admin.tos.units.index'), icon: Swords, permission: 'view_tos_unit' },
            { title: 'Sculpts', href: route('admin.tos.sculpts.index'), icon: FileImage, permission: 'view_tos_sculpt' },
            { title: 'Special Rules', href: route('admin.tos.special_rules.index'), icon: BookOpen, permission: 'view_tos_special_unit_rule' },
            { title: 'Abilities', href: route('admin.tos.abilities.index'), icon: Shield, permission: 'view_tos_ability' },
            { title: 'Actions', href: route('admin.tos.actions.index'), icon: Swords, permission: 'view_tos_action' },
            { title: 'Triggers', href: route('admin.tos.triggers.index'), icon: Swords, permission: 'view_tos_trigger' },
        ],
    },
    {
        title: 'TOS — Cards',
        items: [
            { title: 'Allegiances', href: route('admin.tos.allegiances.index'), icon: Shield, permission: 'view_tos_allegiance' },
            { title: 'Allegiance Cards', href: route('admin.tos.allegiance_cards.index'), icon: BookOpen, permission: 'view_tos_allegiance_card' },
            { title: 'Envoys', href: route('admin.tos.envoys.index'), icon: Shield, permission: 'view_tos_envoy' },
            { title: 'Assets', href: route('admin.tos.assets.index'), icon: Package, permission: 'view_tos_asset' },
            { title: 'Stratagems', href: route('admin.tos.stratagems.index'), icon: Newspaper, permission: 'view_tos_stratagem' },
        ],
    },
    {
        // Super-admin-only diagnostics + tooling. Items use `permission: 'super_admin'`
        // as a sentinel that the hasPermission helper resolves through the role check.
        title: 'Super Admin',
        items: [
            { title: 'Activity Log', href: route('admin.activity.index'), icon: Activity, permission: 'super_admin' },
            { title: 'Announcements', href: route('admin.announcements.index'), icon: Megaphone, permission: 'super_admin' },
            { title: 'Maintenance', href: route('admin.maintenance.index'), icon: ServerCrash, permission: 'super_admin' },
            { title: 'Cache Controls', href: route('admin.cache.index'), icon: Eraser, permission: 'super_admin' },
            { title: 'Schedule', href: route('admin.schedule.index'), icon: Clock, permission: 'super_admin' },
            { title: 'Trash', href: route('admin.trash.index'), icon: Trash2, permission: 'super_admin' },
            { title: 'Failed Jobs', href: route('admin.failed_jobs.index'), icon: AlertTriangle, permission: 'super_admin' },
            { title: 'Feature Flags', href: route('admin.features.index'), icon: Flag, permission: 'super_admin' },
            { title: 'API Tokens', href: route('admin.api_tokens.index'), icon: Key, permission: 'super_admin' },
            { title: 'Sessions', href: route('admin.sessions.index'), icon: MonitorSmartphone, permission: 'super_admin' },
            { title: 'Custom Cards', href: route('admin.custom_cards.index'), icon: ShieldAlert, permission: 'super_admin' },
            { title: 'Image Health', href: route('admin.image_health.index'), icon: ImageOff, permission: 'super_admin' },
            { title: 'Tournament Override', href: route('admin.tournaments.index'), icon: Trophy, permission: 'super_admin' },
            // Log Viewer + Telescope are separate Blade-rendered apps. external=true
            // makes the sidebar use a plain <a> so the browser does a full page nav
            // — Inertia's XHR-then-fallback path leaves the prior URL in place,
            // which breaks Log Viewer's relative API calls.
            { title: 'Logs', href: '/log-viewer', icon: FileText, permission: 'super_admin', external: true },
            { title: 'Telescope', href: '/telescope', icon: TelescopeIcon, permission: 'super_admin', external: true },
        ],
    },
];

const dashboardNav = computed<NavItem[]>(() => [
    {
        items: [
            { title: 'Dashboard', href: route('admin.dashboard'), icon: Gauge },
            // Return to whichever game system the user is currently on (TOS vs Malifaux).
            // home_route is a resolved URL, not a route name.
            { title: '← Back to site', href: page.props.currentGameSystem?.home_route ?? route('index'), icon: ArrowLeft },
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
            <div class="px-2 pb-1 sm:hidden">
                <GameSystemSwitcher />
            </div>
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
