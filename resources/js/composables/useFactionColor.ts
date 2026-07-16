export function useFactionColor(factionSlug: string): string {
    switch (factionSlug) {
        case 'explorers_society':
            return 'explorerssociety';
        case 'ten_thunders':
            return 'tenthunders';
        default:
            return factionSlug;
    }
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
