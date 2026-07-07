import { cn } from '@/lib/utils';

/**
 * Canonical hover treatments for clickable cards, shared across every card
 * grid in the app so lift/duration/shadow/border-tint don't drift file to
 * file. Apply via `:class` at the call site, e.g.
 * `<Card :class="['h-full overflow-hidden', CARD_HOVER]">`. Each constant is
 * routed through `cn()` purely so prettier-plugin-tailwindcss (configured
 * via .prettierrc's `tailwindFunctions`) keeps sorting the classes on every
 * `npm run format` — a bare string wouldn't be picked up by that tooling.
 */

/** Standard hover treatment for a clickable card in a multi-card grid. */
export const CARD_HOVER = cn('transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md');

/**
 * Same treatment, scoped to a `group` ancestor — for cards with a sibling
 * action button (e.g. a delete icon) outside the Card/Link, so hover can't
 * be scoped to the card alone. Confirm the ancestor wrapper carries
 * `class="group ..."` wherever this is used.
 */
export const CARD_HOVER_GROUP = cn(
    'transition-all duration-200 ease-out group-hover:-translate-y-0.5 group-hover:border-primary/30 group-hover:shadow-md',
);

/** Prominent treatment for hero/CTA/single-featured cards. */
export const CARD_HOVER_PROMINENT = cn('transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg');

/** group-hover variant of the prominent tier — large hub tiles with a sibling action. */
export const CARD_HOVER_GROUP_PROMINENT = cn(
    'transition-all duration-200 ease-out group-hover:-translate-y-1 group-hover:border-primary/40 group-hover:shadow-lg',
);

/**
 * Quiet/inert tier — border-tint only, no motion, no shadow. For
 * deliberately lower-emphasis cards (e.g. a completed/non-actionable row)
 * that still shouldn't be a totally dead surface.
 */
export const CARD_HOVER_QUIET = cn('transition-colors duration-200 hover:border-primary/30');
