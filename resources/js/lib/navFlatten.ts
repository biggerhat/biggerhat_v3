import type { NavGroup, NavItem } from '@/types';

/** Flattens a nav tree (group → items) into one flat list, dropping the group wrapper. */
export function flattenNavGroups(groups: NavGroup[]): NavItem[] {
    return groups.flatMap((group) => group.items);
}
