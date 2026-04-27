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
