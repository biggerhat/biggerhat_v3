/**
 * Pure display helpers for the game tracker — formatting and lookup logic with
 * no component state. Shared by Games/Show.vue and its per-phase panels so the
 * decomposition doesn't duplicate this logic. Keep everything here a pure
 * function of its arguments.
 */

interface NamedPlayer {
    user?: { name: string } | null;
    opponent_name?: string | null;
}

/** Human label for a player: account name, else the solo opponent name, else 'Opponent'. */
export function playerName(player: NamedPlayer | undefined): string {
    return player?.user?.name ?? player?.opponent_name ?? 'Opponent';
}

/** Display label for a crew hiring category. */
export function categoryLabel(cat: string): string {
    return { leader: 'Leader', totem: 'Totem', 'in-keyword': 'In Keyword', versatile: 'Versatile', ook: 'Out of Keyword' }[cat] ?? cat;
}

/** Badge colour classes for a crew hiring category. */
export function categoryColor(cat: string): string {
    return (
        {
            leader: 'bg-amber-400/20 text-amber-200',
            totem: 'bg-purple-400/20 text-purple-200',
            'in-keyword': 'bg-green-400/20 text-green-200',
            versatile: 'bg-blue-400/20 text-blue-200',
            ook: 'bg-red-400/20 text-red-200',
        }[cat] ?? ''
    );
}
