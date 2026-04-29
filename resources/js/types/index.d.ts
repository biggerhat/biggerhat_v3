import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
    permissions: string[];
    can_publish_posts: boolean;
    can_access_admin: boolean;
    can_view_tos: boolean;
    is_super_admin: boolean;
    impersonating: { as: { id: number; name: string }; leave_url: string } | null;
    collection_miniature_ids: number[];
    collection_package_ids: number[];
    wishlists: Array<{ id: number; name: string }>;
    wishlist_items: Record<number, { characters: number[]; miniatures: number[]; packages: number[] }>;
    channel_ids: number[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
    badge?: string;
    /**
     * Set to true for links that point at non-Inertia pages (Telescope,
     * Log Viewer, etc.) so the sidebar renders a plain `<a>` and the browser
     * does a full page navigation instead of an Inertia XHR.
     */
    external?: boolean;
}

export interface FactionInfo {
    slug: string;
    name: string;
    color: string;
    logo: string;
}

export interface AllegianceInfo {
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    color: string;
    logo: string;
}

export interface CurrentGameSystem {
    slug: 'malifaux' | 'tos';
    label: string;
    home_route: string;
    switch_to: { slug: 'malifaux' | 'tos'; label: string; home_route: string };
}

export interface AnnouncementBannerData {
    id: number;
    message: string;
    level: 'info' | 'warning' | 'success';
    is_dismissable: boolean;
    link_url: string | null;
    link_label: string | null;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    faction_info: Record<string, FactionInfo>;
    tos_allegiance_info: Record<string, AllegianceInfo>;
    currentGameSystem: CurrentGameSystem;
    announcements: AnnouncementBannerData[];
    ziggy: Config & { location: string };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
