/**
 * Comparing text the way a person means it, for search boxes and for matching names pasted from elsewhere.
 *
 * Card text and names use a typographic apostrophe (U+2019) while people type a plain one, or leave it out: "this action's damage" and
 * "this actions damage" must both find "this action’s damage". So apostrophes of every kind are dropped, accents are folded, case is ignored
 * and runs of white space count as one space. The PHP twin is App\Support\Search (keep the two lists of apostrophes in step).
 */

/** The apostrophe look-alikes: ' ’ ‘ ‛ ` ´ ʼ ʹ ′ and the full-width form. */
const APOSTROPHES = /['’‘‛`´ʼʹ′＇]/g;

export function normalizeText(value: string | null | undefined): string {
    return (value ?? '')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '') // accents: é -> e
        .replace(APOSTROPHES, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/** Does `haystack` contain `needle`, ignoring case, accents, apostrophes and spacing? An empty needle matches everything. */
export function textHas(haystack: string | null | undefined, needle: string | null | undefined): boolean {
    const n = normalizeText(needle);
    return n === '' || normalizeText(haystack).includes(n);
}

/** Are the two the same text, ignoring case, accents, apostrophes and spacing? */
export function sameText(a: string | null | undefined, b: string | null | undefined): boolean {
    return normalizeText(a) === normalizeText(b);
}
