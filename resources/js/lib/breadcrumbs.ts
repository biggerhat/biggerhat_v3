import type { BreadcrumbItemType } from '@/types';

type Props = Record<string, any>;

/** Each entry returns the trail *above* the current page; the last item is the page itself. */
type Builder = (props: Props) => BreadcrumbItemType[] | null;

const crumb = (title: string | null | undefined, href: string): BreadcrumbItemType[] => (title ? [{ title, href }] : []);

/** Trail for `Parent › Item` pages whose item name lives at `props[key][field]`. */
const listDetail =
    (listTitle: string, listRoute: () => string, key: string, field = 'name'): Builder =>
    (props) => {
        const name = props[key]?.[field];

        return name
            ? [
                  { title: listTitle, href: listRoute() },
                  { title: String(name), href: '' },
              ]
            : null;
    };

const campaignPage =
    (label: string): Builder =>
    (props) => {
        const campaign = props.campaign;
        if (!campaign?.name) return null;

        return [
            { title: 'Campaigns', href: route('campaigns.index') },
            { title: campaign.name, href: route('campaigns.show', campaign.id) },
            { title: label, href: '' },
        ];
    };

/**
 * Breadcrumb trails for detail pages, derived from the Inertia component name + its props, so pages
 * don't each have to pass `breadcrumbs` to the layout. Pages that aren't listed (index/list pages,
 * dashboards, forms) get no trail — they're one level below the sidebar already.
 */
const BUILDERS: Record<string, Builder> = {
    'Characters/View': (props) => {
        const character = props.character;
        if (!character?.display_name) return null;
        const faction = props.faction_info?.[character.faction];

        return [...(faction ? crumb(faction.name, route('factions.view', character.faction)) : []), { title: character.display_name, href: '' }];
    },
    'Keywords/View': listDetail('Keywords', () => route('keywords.index'), 'keyword'),
    'Upgrades/View': (props) => {
        const upgrade = props.upgrade;
        if (!upgrade?.name) return null;
        const isCrew = upgrade.domain === 'crew';

        return [
            { title: isCrew ? 'Crew Cards' : 'Upgrades', href: isCrew ? route('upgrades.crew.index') : route('upgrades.character.index') },
            { title: upgrade.name, href: '' },
        ];
    },
    'Packages/View': listDetail('Packages', () => route('packages.index'), 'package'),
    'Blog/View': listDetail('Articles', () => route('blog.index'), 'post', 'title'),
    'News/View': listDetail('Site News', () => route('news.index'), 'post', 'title'),
    'Channels/View': listDetail('Across the Aethervox', () => route('channels.index'), 'channel'),
    'Tournaments/View': listDetail('Tournaments', () => route('tournaments.index'), 'tournament'),
    'Tournaments/Manage': listDetail('Tournaments', () => route('tournaments.index'), 'tournament'),
    'Tools/CrewBuilder/View': listDetail('Crew Builder', () => route('tools.crew_builder.index'), 'build'),

    'TOS/Units/View': listDetail('Units', () => route('tos.units.index'), 'unit'),
    'TOS/Allegiances/View': listDetail('Allegiances', () => route('tos.allegiances.index'), 'allegiance'),
    'TOS/Assets/View': listDetail('Assets', () => route('tos.assets.index'), 'asset'),
    'TOS/Stratagems/View': listDetail('Stratagems', () => route('tos.stratagems.index'), 'stratagem'),
    'TOS/AllegianceCards/View': listDetail('Allegiance Cards', () => route('tos.allegiance_cards.index'), 'card'),

    'Cooperative/Encounter': (props) =>
        props.encounter?.name
            ? [
                  { title: 'Cooperative', href: route('cooperative.index') },
                  { title: props.encounter.name, href: '' },
              ]
            : null,

    'Campaigns/Show': (props) =>
        props.campaign?.name
            ? [
                  { title: 'Campaigns', href: route('campaigns.index') },
                  { title: props.campaign.name, href: '' },
              ]
            : null,
    'Campaigns/Aftermath': campaignPage('Aftermath'),
    'Campaigns/ArsenalSheet': campaignPage('Arsenal'),
    'Campaigns/LeaderBuilder': campaignPage('Leader'),
    'Campaigns/LogGame': campaignPage('Log a game'),
    'Campaigns/NewGame': campaignPage('New game'),
    'Campaigns/Settings': campaignPage('Settings'),
    'Campaigns/StartingArsenal': campaignPage('Starting arsenal'),
    'Campaigns/WeeklyHire': campaignPage('Weekly hire'),
    'Campaigns/GameRecap': campaignPage('Recap'),
};

export function deriveBreadcrumbs(component: string, props: Props): BreadcrumbItemType[] {
    const trail = BUILDERS[component]?.(props);
    if (!trail?.length) return [];

    return [{ title: 'Home', href: route('index') }, ...trail];
}
