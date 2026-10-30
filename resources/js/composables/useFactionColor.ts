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

/** Faction colours that need dark text to reach WCAG AA (white on them is < 4.5:1). */
const LIGHT_FACTIONS = new Set(['outcasts', 'abyssinia', 'explorerssociety', 'resurrectionists']);

/**
 * Readable text colour class for a solid faction-coloured background. Use for elements whose
 * background is applied inline (so the `bg-<faction>` stylesheet rule can't flip the text itself).
 */
export function factionForeground(faction: string | null): string {
    if (!faction) return 'text-white';

    return LIGHT_FACTIONS.has(useFactionColor(faction.toLowerCase())) ? 'text-neutral-900' : 'text-white';
}
