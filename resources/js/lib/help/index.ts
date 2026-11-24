import collection from './topics/collection.ts';
import crewBuilder from './topics/crewBuilder.ts';
import gameTracker from './topics/gameTracker.ts';
import tabletop from './topics/tabletop.ts';
import { SHORTCUTS_ID, type HelpTab, type HelpTopic } from './types.ts';

export * from './types.ts';

/** Every help topic, in the order the help index lists them. Add a new feature's topic here (and to App\Support\Help\HelpTopics). */
export const HELP_TOPICS: HelpTopic[] = [crewBuilder, gameTracker, collection, tabletop];

export const helpTopic = (slug: string): HelpTopic | undefined => HELP_TOPICS.find((t) => t.slug === slug);

/** A topic's sections as tabs, with its shortcuts last. */
export const helpTabs = (topic: HelpTopic): HelpTab[] => [
    ...topic.sections.map((s) => ({ id: s.id, title: s.title })),
    ...(topic.shortcuts?.length ? [{ id: SHORTCUTS_ID, title: 'Shortcuts' }] : []),
];
