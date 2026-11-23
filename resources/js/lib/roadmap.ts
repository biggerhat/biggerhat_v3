/**
 * The public roadmap (/roadmap). Edit this file to change the page: move an item between groups, add one, or add a line to `shipped`.
 * Keep each item to plain words a player would use, and promise direction, not dates. Anything that is not decided stays in "Later".
 */
export type RoadmapStage = 'now' | 'next' | 'later';

export interface RoadmapItem {
    title: string;
    /** One or two sentences, for a player. */
    body: string;
    /** The part of the site it belongs to. */
    area: string;
}

export interface ShippedItem {
    /** Month the change reached the site, e.g. "November 2026". */
    when: string;
    title: string;
}

export const ROADMAP_UPDATED = 'November 2026';

export const ROADMAP_STAGES: { key: RoadmapStage; title: string; intro: string }[] = [
    { key: 'now', title: 'Now', intro: 'Being built or tested at the moment.' },
    { key: 'next', title: 'Next', intro: 'Planned, and likely to come after what is in progress.' },
    { key: 'later', title: 'Later', intro: 'Ideas we like but have not scheduled. They may change or not happen.' },
];

export const ROADMAP: Record<RoadmapStage, RoadmapItem[]> = {
    now: [
        {
            area: 'Tabletop',
            title: 'Tabletop for everyone',
            body: 'A 36 inch board with to-scale bases, terrain, sight guidelines, a Fate deck and saved tables. It is in preview while we fix what testers find, then it opens to all players.',
        },
        {
            area: 'Game Tracker',
            title: 'Playing a whole game on the tabletop',
            body: "Choose how a game is played, deploy your crews on the board in the rulebook's order, and keep health, activations and Fate in one place.",
        },
        {
            area: 'Campaign',
            title: 'Campaign Mode polish',
            body: 'Crew cards, advancements and the arsenal sheet are in use. We are fixing what campaign players report as they play.',
        },
    ],
    next: [
        {
            area: 'Cooperative',
            title: 'Cooperative Mode play tracker',
            body: 'The encounters and non-player classes can be browsed now. Next is a tracker to run a cooperative encounter: the non-player crew, its Fate deck and each phase.',
        },
        {
            area: 'Tabletop',
            title: 'A Fate deck you can pop out',
            body: 'Keep the Fate deck and your hand open beside the board instead of in the side column.',
        },
    ],
    later: [
        {
            area: 'Everywhere',
            title: 'An app for your phone',
            body: 'A wrapper so the site installs on iOS and Android and works better at the table.',
        },
        {
            area: 'Tournaments',
            title: 'More games and event types',
            body: 'The tournament tracker already runs with rulesets, so other games and team events can be added as they are asked for.',
        },
    ],
};

/** Newest first. */
export const SHIPPED: ShippedItem[] = [
    { when: 'November 2026', title: 'Search ignores apostrophes, so "this actions damage" finds "this action\'s damage"' },
    { when: 'November 2026', title: 'Play mode and deployment: set up a game on the tabletop' },
    { when: 'November 2026', title: 'Attacker is red and defender is blue on the board' },
    { when: 'November 2026', title: 'Fate hand fits its width; sight guidelines turn off with G, Escape or a double click' },
    { when: 'November 2026', title: 'Remains markers are placed against the killed model' },
    { when: 'November 2026', title: 'Miniature sculpts are numbered ("Sculpt 1", "Sculpt 2") again after an update' },
];
