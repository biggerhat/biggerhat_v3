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

/** Same choice as {@link factionForeground}, as a CSS colour for inline `style` bindings. */
export function factionForegroundColor(faction: string | null): string {
    return factionForeground(faction) === 'text-neutral-900' ? '#111827' : '#ffffff';
}

/**
 * Classes for a neutral surface carrying the faction as a coloured spine + tint (see `.faction-spine` in
 * app.css). Pair with `bg-card border`. Pass `row: true` for a table row.
 */
export function factionSpine(faction: string | null, options: { row?: boolean } = {}): string {
    const base = options.row ? 'faction-spine-row' : 'faction-spine';
    if (!faction) return base;

    return `${base} faction-${useFactionColor(faction.toLowerCase())}`;
}

/**
 * Deep faction-tinted surface for rows that keep white text (the game tracker). Unlike
 * `factionBackground()` it never needs a dark-text flip, and reads calmer than a saturated fill.
 */
export function factionDeep(faction: string | null): string {
    const base = 'faction-deep';
    if (!faction) return base;

    return `${base} faction-${useFactionColor(faction.toLowerCase())}`;
}
