import type { NavGroup, NavItem } from '@/types';

// Base is only used to parse relative hrefs; it is never compared.
const BASE = 'http://nav.local';

interface ParsedHref {
    path: string;
    query: [string, string][];
}

function parse(href: string): ParsedHref {
    const url = new URL(href, BASE);
    return { path: url.pathname.replace(/\/+$/, '') || '/', query: [...url.searchParams.entries()] };
}

/**
 * Score how well `href` matches the current URL, or -1 when it doesn't.
 *
 * - Path must be equal, or a parent of the current path (so an index link
 *   stays lit on its edit/create pages). Single-segment paths (`/`, `/admin`,
 *   `/tos`) only match exactly, otherwise a landing link would light up
 *   everywhere beneath it.
 * - Every query param on the link must be present on the current URL
 *   (filtered shortcuts such as `?game_mode=campaign` only light up while
 *   that filter is applied).
 * - More specific links (longer path, more params) score higher.
 */
export function matchScore(href: string, currentUrl: string): number {
    const link = parse(href);
    const current = parse(currentUrl);

    const segments = link.path.split('/').filter(Boolean).length;
    const exact = link.path === current.path;
    const parent = segments >= 2 && current.path.startsWith(link.path + '/');
    if (!exact && !parent) return -1;

    const currentParams = new Map(current.query);
    if (!link.query.every(([key, value]) => currentParams.get(key) === value)) return -1;

    return link.path.length * 10 + link.query.length * 5 + (exact ? 1 : 0);
}

/** The single best-matching internal item across all groups, or null. */
export function findActiveItem(groups: NavGroup[], currentUrl: string): NavItem | null {
    let best: NavItem | null = null;
    let bestScore = -1;
    for (const group of groups) {
        for (const item of group.items) {
            if (item.external) continue;
            const score = matchScore(item.href, currentUrl);
            if (score > bestScore) {
                best = item;
                bestScore = score;
            }
        }
    }
    return best;
}
