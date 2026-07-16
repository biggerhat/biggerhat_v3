import { FactionEnum } from '@/types/generated/FactionEnum';

const FACTION_COLOR_BY_SLUG: Record<string, string> = Object.fromEntries(Object.values(FactionEnum).map((f) => [f.value, f.color]));

export function useFactionColor(factionSlug: string): string {
    return FACTION_COLOR_BY_SLUG[factionSlug] ?? factionSlug;
}

/**
 * Tailwind background class for a faction tile, e.g. `bg-arcanists`.
 * Returns an empty string for null/missing factions. Standalone — used by
 * standings tables, player list rows, etc.
 */
export function factionBackground(faction: string | null): string {
    if (!faction) return '';

    return `bg-${useFactionColor(faction.toLowerCase())}`;
}
