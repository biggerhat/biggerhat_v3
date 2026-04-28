/**
 * Shared TypeScript shapes for TOS pages.
 *
 * The page-data controllers all return Laravel paginators with the same
 * envelope, plus enum-driven select option lists keyed { name, value }.
 * Hoisting these here removes ~14 LOC of redeclaration from every Index.vue
 * and gives a single home for any new shared shape going forward.
 *
 * Per-entity shapes intentionally stay in their consuming pages — different
 * controllers select different columns, and forcing a single canonical
 * Unit/Asset/etc. shape would over-couple the surface.
 */

export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    from: number | null;
    to: number | null;
}

export interface TosSelectOption {
    name: string;
    value: string;
}

/**
 * Minimal Sculpt shape — enough to render a card link + flip-image. Recurs
 * in every page that lists units (Index, Allegiances/View, Companies/View,
 * Compare, Search). Per-page wrappers can extend this when they need
 * release_date / box_reference / store_link.
 */
export interface TosSculpt {
    id: number;
    slug: string;
    name: string | null;
    front_image?: string | null;
    back_image?: string | null;
    combination_image?: string | null;
}

/**
 * Standard Unit Side projection (4 AVs only — abilities/actions are loaded
 * on the unit-view page, not on roster pages).
 */
export interface TosUnitSide {
    id: number;
    side: string;
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
}

/**
 * Special Unit Rule pivot row as exposed on a Unit. `pivot.parameters` is
 * present on admin form payloads but omitted on read-only roster views.
 */
export interface TosSpecialRule {
    id: number;
    slug: string;
    name: string;
    pivot?: { parameters: Record<string, unknown> | null };
}
