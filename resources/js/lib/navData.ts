/**
 * Pure nav-tree builders shared by AppSidebar.vue (the actual sidebar) and
 * AppSidebarHeader.vue (the command palette, via lib/navFlatten.ts). Single
 * source of truth for titles/routes/icons so the two don't drift. Every
 * function takes plain values (not refs) and returns a fresh, non-reactive
 * tree; callers wrap the call in their own `computed()` for reactivity.
 */
import type { NavGroup, NavItem } from '@/types';
import {
    ArrowUpCircle,
    BarChart3,
    BookOpen,
    Bot,
    Calendar,
    Coins,
    Dice6,
    FileImage,
    Gauge,
    Heart,
    Home,
    KeyRound,
    Library,
    Map,
    Megaphone,
    Newspaper,
    Package,
    Puzzle,
    Radio,
    Radius,
    Scale,
    Shield,
    ShieldCheck,
    Swords,
    TextSearch,
    Trophy,
    Users,
    Zap,
} from 'lucide-vue-next';

export interface MainNavContext {
    isAuthenticated: boolean;
    canAccessAdmin: boolean;
    campaignFeaturesEnabled: boolean;
    hasChannels: boolean;
    factionItems: NavItem[];
}

export function buildMainNav(ctx: MainNavContext): NavGroup[] {
    return [
        {
            items: [
                {
                    // Logo-implicit today (no dedicated sidebar row) but a real,
                    // searchable destination for the command palette.
                    title: 'Home / Browse',
                    href: route('index'),
                    icon: Home,
                    keywords: 'home characters factions database browse malifaux',
                },
                {
                    title: 'Advanced Search',
                    href: route('search.view'),
                    icon: TextSearch,
                    keywords: 'advanced search filter',
                },
                ...(ctx.canAccessAdmin
                    ? [
                          {
                              title: 'Admin',
                              href: route('admin.dashboard'),
                              icon: ShieldCheck,
                          },
                      ]
                    : []),
            ],
        },
        {
            // Tools anyone can use — no auth required — as opposed to "My Hat"
            // below, which is your actual account history. Kept as a separate
            // group so guests never see a "My Hat" section implying
            // personalization they don't have.
            title: 'Play',
            collapsible: true,
            collapsed: false,
            items: [
                {
                    // Sidebar "Crew Builder" sends you to the editor (the most common
                    // intent). The community-crews browse list is reachable via a
                    // dedicated entry below + the URL /tools/crew-builder/ for any
                    // existing bookmarks.
                    title: 'Crew Builder',
                    href: route('tools.crew_builder.editor'),
                    icon: Swords,
                    keywords: 'crew builder build',
                },
                {
                    title: 'Game Tracker',
                    href: route('games.index'),
                    icon: Swords,
                    badge: 'Beta',
                },
                {
                    title: 'Tournament Tracker',
                    href: route('tournaments.index'),
                    icon: Trophy,
                    badge: 'Beta',
                },
                // Authed users with campaign access see the full Campaigns link
                // (Beta badge now that the feature is approaching open beta).
                // Authed users WITHOUT access see a teaser entry routing to the
                // public coming-soon page — keeps discovery alive while gating
                // the actual feature.
                ...(ctx.isAuthenticated && ctx.campaignFeaturesEnabled
                    ? [
                          {
                              title: 'Campaigns',
                              href: route('campaigns.index'),
                              icon: Trophy,
                              badge: 'Beta',
                          },
                      ]
                    : ctx.isAuthenticated
                      ? [
                            {
                                title: 'Campaigns',
                                href: route('campaigns.preview'),
                                icon: Trophy,
                                badge: 'Soon',
                            },
                        ]
                      : []),
            ],
        },
        ...buildMyHatNav({ isAuthenticated: ctx.isAuthenticated, hasChannels: ctx.hasChannels }),
        {
            title: 'Community',
            collapsible: true,
            collapsed: false,
            items: [
                {
                    title: 'Articles',
                    href: route('blog.index'),
                    icon: Newspaper,
                },
                {
                    title: 'Site News',
                    href: route('news.index'),
                    icon: Megaphone,
                },
                {
                    title: 'Across the Aethervox',
                    href: route('channels.index'),
                    icon: Radio,
                },
            ],
        },
        {
            title: 'Factions',
            collapsible: true,
            collapsed: false,
            items: ctx.factionItems,
        },
        {
            title: 'Tools',
            collapsible: true,
            collapsed: false,
            items: [
                {
                    title: 'Community Crews',
                    href: route('tools.crew_builder.index'),
                    icon: Library,
                    keywords: 'community crews browse shared',
                },
                {
                    title: 'Compare Characters',
                    href: route('tools.compare'),
                    icon: Scale,
                    keywords: 'compare characters stat side by side',
                },
                {
                    title: 'Scenario Generator',
                    href: route('tools.scenario_generator'),
                    icon: Dice6,
                    keywords: 'scenario generator random strategy',
                },
                {
                    title: 'Scheme Paths',
                    href: route('tools.scheme_paths'),
                    icon: Map,
                    keywords: 'scheme paths chain planner',
                },
                {
                    // Was mislabeled 'Random Character' despite routing to the
                    // filtered picker. 'characters.random' (true single-click
                    // random) is a distinct, palette-only action with no sidebar
                    // entry of its own — see AppSidebarHeader.vue.
                    title: 'Random Character Picker',
                    href: route('tools.random_character'),
                    icon: Dice6,
                    keywords: 'random character picker filtered faction keyword characteristic cost dice',
                },
                {
                    title: 'Bonanza Loot Deck',
                    href: route('tools.bonanza_loot_deck'),
                    icon: Coins,
                    keywords: 'bonanza brawl loot deck solo format',
                },
                {
                    title: 'Hat Gamin Bot',
                    href: route('tools.hat_gamin'),
                    icon: Bot,
                    keywords: 'hat gamin bot chat assistant ai',
                },
            ],
        },
        {
            title: 'References',
            collapsible: true,
            collapsed: false,
            items: [
                // Order intentionally mirrors TOS sidebar: abilities (passive) →
                // actions (AP-spending) → triggers (action modifiers).
                {
                    title: 'Abilities',
                    href: route('abilities.index'),
                    icon: Shield,
                },
                {
                    title: 'Actions',
                    href: route('actions.index'),
                    icon: Swords,
                },
                {
                    title: 'Triggers',
                    href: route('triggers.index'),
                    icon: Swords,
                },
                {
                    title: 'Keywords',
                    href: route('keywords.index'),
                    icon: KeyRound,
                },
                {
                    title: 'Markers',
                    href: route('markers.index'),
                    icon: Radius,
                },
                {
                    title: 'Tokens',
                    href: route('tokens.index'),
                    icon: Puzzle,
                },
                {
                    title: 'Crew Cards',
                    href: route('upgrades.crew.index'),
                    icon: ArrowUpCircle,
                    keywords: 'crew cards crew upgrades',
                },
                {
                    title: 'Upgrades',
                    href: route('upgrades.character.index'),
                    icon: ArrowUpCircle,
                    keywords: 'character upgrades',
                },
                {
                    title: 'Packages',
                    href: route('packages.index'),
                    icon: Package,
                },
                {
                    title: 'Lore',
                    href: route('lores.index'),
                    icon: BookOpen,
                },
                {
                    title: 'Build Instructions',
                    href: route('blueprints.index'),
                    icon: FileImage,
                },
                {
                    title: 'Gaining Grounds',
                    href: route('seasons.index'),
                    icon: Calendar,
                },
            ],
        },
    ];
}

export interface TosMyStuffContext {
    isAuthenticated: boolean;
}

/** Company/Garrison Builder — TOS's "personal" links, mirroring buildMyHatNav's role for Malifaux. */
export function buildTosMyStuff(ctx: TosMyStuffContext): NavItem[] {
    if (!ctx.isAuthenticated) return [];
    return [
        // Same destination as Malifaux's "My Hub" entry below — the hub covers
        // both game systems, so it's not TOS- or Malifaux-specific.
        { title: 'My Hub', href: route('overview'), icon: Gauge, keywords: 'dashboard overview hub settings profile' },
        { title: 'My Collection', href: route('tos.collection.index'), icon: Package, keywords: 'collection unit sculpts owned tos' },
        // Same destination as Malifaux's "My Wishlists" — Wishlist items can
        // hold either game system's content, so the list itself isn't TOS-specific.
        { title: 'My Wishlists', href: route('wishlists.index'), icon: Heart },
        { title: 'Company Builder', href: route('tos.companies.index'), icon: Users, keywords: 'company builder tos build' },
        { title: 'Garrison Builder', href: route('tos.garrisons.index'), icon: Shield, keywords: 'garrison builder tos tournament pool' },
    ];
}

export interface TosNavContext {
    isAuthenticated: boolean;
    canAccessAdmin: boolean;
    allegianceItems: NavItem[];
}

export function buildTosNav(ctx: TosNavContext): NavGroup[] {
    const tosMyStuff = buildTosMyStuff({ isAuthenticated: ctx.isAuthenticated });

    return [
        {
            items: [
                // Logo-implicit today (no dedicated sidebar row) but a real,
                // searchable destination for the command palette — mirrors
                // Malifaux's "Home / Browse" entry for consistency.
                {
                    title: 'Home / Browse',
                    href: route('tos.index'),
                    icon: Home,
                    keywords: 'home units allegiances database browse tos the other side',
                },
                { title: 'Advanced Search', href: route('tos.search'), icon: TextSearch, keywords: 'advanced search filter tos' },
                ...(ctx.canAccessAdmin
                    ? [
                          {
                              title: 'Admin',
                              href: route('admin.dashboard'),
                              icon: ShieldCheck,
                          },
                      ]
                    : []),
            ],
        },
        ...(tosMyStuff.length > 0
            ? [
                  {
                      title: 'My Hat',
                      collapsible: true,
                      collapsed: false,
                      items: tosMyStuff,
                  },
              ]
            : []),
        {
            title: 'Allegiances',
            collapsible: true,
            collapsed: false,
            items: [
                { title: 'All Allegiances', href: route('tos.allegiances.index'), icon: Shield, keywords: 'allegiances factions tos' },
                // Type-pooled rosters — pull every Earth- (or Malifaux-) typed
                // unit plus the matching Neutral pool. Sit above the per-allegiance
                // entries so users can pick "show me everything Earth" before
                // narrowing to a specific Company.
                { title: 'Earth Side', href: route('tos.allegiances.viewByType', 'earth'), icon: Shield },
                { title: 'Malifaux Side', href: route('tos.allegiances.viewByType', 'malifaux'), icon: Shield },
                ...ctx.allegianceItems,
            ],
        },
        {
            title: 'References',
            collapsible: true,
            collapsed: false,
            items: [
                { title: 'Units', href: route('tos.units.index'), icon: Swords, keywords: 'units tos models' },
                {
                    title: 'Special Rules',
                    href: route('tos.special_rules.index'),
                    icon: BookOpen,
                    keywords: 'special rules tos commander titan fireteam squad',
                },
                { title: 'Abilities', href: route('tos.abilities.index'), icon: Zap, keywords: 'abilities tos' },
                { title: 'Actions', href: route('tos.actions.index'), icon: Swords, keywords: 'actions tos' },
                { title: 'Triggers', href: route('tos.triggers.index'), icon: Swords, keywords: 'triggers tos' },
                { title: 'Allegiance Cards', href: route('tos.allegiance_cards.index'), icon: BookOpen, keywords: 'allegiance cards tos' },
                { title: 'Assets', href: route('tos.assets.index'), icon: Package, keywords: 'assets tos vehicles gear' },
                { title: 'Stratagems', href: route('tos.stratagems.index'), icon: Newspaper, keywords: 'stratagems tos' },
                { title: 'Compare Units', href: route('tos.compare'), icon: Scale, keywords: 'compare units tos' },
            ],
        },
    ];
}

export interface MyHatNavContext {
    isAuthenticated: boolean;
    hasChannels: boolean;
}

export function buildMyHatNav(ctx: MyHatNavContext): NavGroup[] {
    if (!ctx.isAuthenticated) return [];
    const items: NavItem[] = [
        {
            // Same destination as TOS's "My Hub" entry in buildTosMyStuff — the
            // hub covers both game systems, so it's not Malifaux-specific either.
            title: 'My Hub',
            href: route('overview'),
            icon: Gauge,
            keywords: 'dashboard overview hub settings profile',
        },
        {
            title: 'My Collection',
            href: route('collection.index'),
            icon: Library,
            keywords: 'collection miniatures owned',
        },
        {
            title: 'My Stats',
            href: route('stats.my'),
            icon: BarChart3,
            keywords: 'stats win rate record',
        },
        {
            title: 'My Wishlists',
            href: route('wishlists.index'),
            icon: Heart,
        },
    ];

    if (ctx.hasChannels) {
        items.push({
            title: 'My Channels',
            href: route('channels.my'),
            icon: Radio,
        });
    }

    return [
        {
            title: 'My Hat',
            collapsible: true,
            collapsed: false,
            items,
        },
    ];
}
