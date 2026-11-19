/**
 * The shape of a help topic. A topic is one feature's help: sections of "how it works" items, and optionally its keyboard and pointer shortcuts.
 * Topics are plain data in lib/help/topics/<name>.ts and registered in lib/help/index.ts; the help dialog, the help pages and any tooltips read them.
 *
 * Write help about how the feature works, in the words on its screen (button names, tab names), not about the rules of the game: link the
 * rulebook for those. Keep it true: when a feature changes, change its topic in the same change (see docs/features/help.md).
 */
/**
 * A picture for a help item. Images are screenshots made by `scripts/ui-audit/tournaments/flows38.cjs` (docs/features/help.md says how to remake
 * them) and live in public/images/help (not public/help: a folder there would shadow the /help pages). `callouts` is the numbered legend: the screenshot has the same numbers drawn on it, in the same order, and the
 * list is also the text version of the picture, so write it so it makes sense without seeing the image.
 */
export interface HelpImage {
    /** Path under public/images/help, e.g. 'tabletop/board.webp'. */
    src: string;
    /** What the picture shows, for someone who cannot see it. Not a repeat of the item's text. */
    alt: string;
    callouts?: string[];
}

export interface HelpItem {
    title: string;
    body: string;
    image?: HelpImage;
}

export interface HelpSection {
    /** Used in the address (#help-<id>) and by `section` in the dialog, so keep it short and stable. */
    id: string;
    title: string;
    intro: string;
    items: HelpItem[];
}

export interface Shortcut {
    keys: string[];
    does: string;
}

export interface ShortcutGroup {
    title: string;
    note?: string;
    shortcuts: Shortcut[];
}

export interface HelpTopic {
    /** The address segment (/help/<slug>); matches App\Support\Help\HelpTopics::SLUGS. */
    slug: string;
    title: string;
    /** One sentence, shown on the help index. */
    summary: string;
    /** Where the feature itself lives, as a route name, for the "Open <feature>" link. */
    route: string;
    sections: HelpSection[];
    shortcuts?: ShortcutGroup[];
}

/** The id of the extra section a topic gets when it has shortcuts. */
export const SHORTCUTS_ID = 'shortcuts';

export interface HelpTab {
    id: string;
    title: string;
}
