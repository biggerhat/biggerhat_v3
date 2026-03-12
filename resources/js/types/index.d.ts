import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
    permissions: string[];
    can_publish_posts: boolean;
    collection_miniature_ids: number[];
    collection_package_ids: number[];
    wishlists: Array<{ id: number; name: string }>;
    wishlist_items: Record<number, { characters: number[]; miniatures: number[]; packages: number[] }>;
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
}

export interface FactionInfo {
    slug: string;
    name: string;
    color: string;
    logo: string;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    faction_info: Record<string, FactionInfo>;
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
