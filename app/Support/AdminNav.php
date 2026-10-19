<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Feedback;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\TOS\Ability as TosAbility;
use App\Models\TOS\Allegiance as TosAllegiance;
use App\Models\TOS\AllegianceCard as TosAllegianceCard;
use App\Models\TOS\Asset as TosAsset;
use App\Models\TOS\SpecialUnitRule as TosSpecialUnitRule;
use App\Models\TOS\Stratagem as TosStratagem;
use App\Models\TOS\Unit as TosUnit;
use App\Models\Upgrade;
use App\Models\User;

/**
 * Single source of truth for admin navigation — the sidebar, the dashboard
 * tiles and the Cmd-K palette all render from this list. `icon` is a lucide
 * icon name resolved client-side (resources/js/lib/adminNavIcons.ts).
 * `permission` is a pipe-separated any-of list, or the literal `super_admin`.
 */
class AdminNav
{
    /**
     * Groups holding only the items the user may see (empty groups dropped).
     * Counts are never included — the dashboard resolves those itself.
     *
     * @return array<int, array{title: string, description: string, items: array<int, array{key: string, title: string, href: string, icon: string, external: bool}>}>
     */
    public static function forUser(?User $user): array
    {
        $groups = [];
        foreach (self::definition() as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (! self::allows($user, $item['permission'])) {
                    continue;
                }
                $items[] = [
                    'key' => $item['key'],
                    'title' => $item['title'],
                    'href' => $item['href'],
                    'icon' => $item['icon'],
                    'external' => $item['external'],
                ];
            }
            if ($items !== []) {
                $groups[] = ['title' => $group['title'], 'description' => $group['description'], 'items' => $items];
            }
        }

        return $groups;
    }

    /** Pipe-separated any-of permission check; `super_admin` means the role itself. */
    public static function allows(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }

        foreach (explode('|', $permission) as $name) {
            if ($name !== 'super_admin' && $user->can($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{title: string, description: string, items: array<int, array{key: string, title: string, href: string, icon: string, permission: string, external: bool, count: (\Closure(): int)|null}>}>
     */
    public static function definition(): array
    {
        return [
            [
                'title' => 'Game Data',
                'description' => 'Characters, rules, upgrades, and board pieces.',
                'items' => [
                    ['key' => 'game_data_characters', 'title' => 'Characters', 'href' => route('admin.characters.index'), 'icon' => 'BookOpen', 'permission' => 'view_character', 'external' => false, 'count' => fn () => Character::count()],
                    ['key' => 'game_data_miniatures', 'title' => 'Miniatures', 'href' => route('admin.miniatures.index'), 'icon' => 'BookOpen', 'permission' => 'view_miniature', 'external' => false, 'count' => fn () => Miniature::count()],
                    ['key' => 'game_data_actions', 'title' => 'Actions', 'href' => route('admin.actions.index'), 'icon' => 'Swords', 'permission' => 'view_action', 'external' => false, 'count' => null],
                    ['key' => 'game_data_abilities', 'title' => 'Abilities', 'href' => route('admin.abilities.index'), 'icon' => 'Shield', 'permission' => 'view_ability', 'external' => false, 'count' => null],
                    ['key' => 'game_data_triggers', 'title' => 'Triggers', 'href' => route('admin.triggers.index'), 'icon' => 'Swords', 'permission' => 'view_trigger', 'external' => false, 'count' => null],
                    ['key' => 'game_data_keywords', 'title' => 'Keywords', 'href' => route('admin.keywords.index'), 'icon' => 'KeyRound', 'permission' => 'view_keyword', 'external' => false, 'count' => fn () => Keyword::count()],
                    ['key' => 'game_data_characteristics', 'title' => 'Characteristics', 'href' => route('admin.characteristics.index'), 'icon' => 'BookOpen', 'permission' => 'view_characteristic', 'external' => false, 'count' => null],
                    ['key' => 'game_data_upgrades', 'title' => 'Upgrades', 'href' => route('admin.upgrades.index'), 'icon' => 'ArrowUpCircle', 'permission' => 'view_upgrade', 'external' => false, 'count' => fn () => Upgrade::count()],
                    ['key' => 'game_data_crew_cards', 'title' => 'Crew Cards', 'href' => route('admin.crews.index'), 'icon' => 'ArrowUpCircle', 'permission' => 'view_crew', 'external' => false, 'count' => null],
                    ['key' => 'game_data_tokens', 'title' => 'Tokens', 'href' => route('admin.tokens.index'), 'icon' => 'Puzzle', 'permission' => 'view_token', 'external' => false, 'count' => null],
                    ['key' => 'game_data_markers', 'title' => 'Markers', 'href' => route('admin.markers.index'), 'icon' => 'Radius', 'permission' => 'view_marker', 'external' => false, 'count' => null],
                    ['key' => 'game_data_schemes', 'title' => 'Schemes', 'href' => route('admin.schemes.index'), 'icon' => 'BookOpen', 'permission' => 'view_scheme', 'external' => false, 'count' => null],
                    ['key' => 'game_data_strategies', 'title' => 'Strategies', 'href' => route('admin.strategies.index'), 'icon' => 'BookOpen', 'permission' => 'view_strategy', 'external' => false, 'count' => null],
                    ['key' => 'game_data_bonanza_loot_cards', 'title' => 'Bonanza Loot Cards', 'href' => route('admin.loot_cards.index'), 'icon' => 'Coins', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                ],
            ],
            [
                'title' => 'Content',
                'description' => 'Articles, news, lore, blueprints, and packages.',
                'items' => [
                    ['key' => 'content_articles', 'title' => 'Articles', 'href' => route('admin.blog.posts.index'), 'icon' => 'Newspaper', 'permission' => 'create_posts|edit_posts', 'external' => false, 'count' => fn () => BlogPost::count()],
                    ['key' => 'content_article_categories', 'title' => 'Article Categories', 'href' => route('admin.blog.categories.index'), 'icon' => 'Newspaper', 'permission' => 'create_posts|edit_posts', 'external' => false, 'count' => null],
                    ['key' => 'content_site_news', 'title' => 'Site News', 'href' => route('admin.news.posts.index'), 'icon' => 'Megaphone', 'permission' => 'manage_news', 'external' => false, 'count' => null],
                    ['key' => 'content_news_categories', 'title' => 'News Categories', 'href' => route('admin.news.categories.index'), 'icon' => 'Megaphone', 'permission' => 'manage_news', 'external' => false, 'count' => null],
                    ['key' => 'content_lore', 'title' => 'Lore', 'href' => route('admin.lores.index'), 'icon' => 'BookOpen', 'permission' => 'view_lore', 'external' => false, 'count' => null],
                    ['key' => 'content_lore_media', 'title' => 'Lore Media', 'href' => route('admin.lore_media.index'), 'icon' => 'BookOpen', 'permission' => 'view_lore', 'external' => false, 'count' => null],
                    ['key' => 'content_blueprints', 'title' => 'Blueprints', 'href' => route('admin.blueprints.index'), 'icon' => 'FileImage', 'permission' => 'view_blueprint', 'external' => false, 'count' => null],
                    ['key' => 'content_packages', 'title' => 'Packages', 'href' => route('admin.packages.index'), 'icon' => 'Package', 'permission' => 'view_package', 'external' => false, 'count' => null],
                    ['key' => 'content_announcements', 'title' => 'Announcements', 'href' => route('admin.announcements.index'), 'icon' => 'Megaphone', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                ],
            ],
            [
                'title' => 'Community',
                'description' => 'Channels, feedback, and external links.',
                'items' => [
                    ['key' => 'community_channels', 'title' => 'Channels', 'href' => route('admin.channels.index'), 'icon' => 'Radio', 'permission' => 'view_channel', 'external' => false, 'count' => null],
                    ['key' => 'community_transmissions', 'title' => 'Transmissions', 'href' => route('admin.transmissions.index'), 'icon' => 'Radio', 'permission' => 'view_channel', 'external' => false, 'count' => null],
                    ['key' => 'community_pod_links', 'title' => 'POD Links', 'href' => route('admin.pod_links.index'), 'icon' => 'ExternalLink', 'permission' => 'view_pod_link', 'external' => false, 'count' => null],
                    ['key' => 'community_feedback', 'title' => 'Feedback', 'href' => route('admin.feedback.index'), 'icon' => 'MessageSquareText', 'permission' => 'view_feedback', 'external' => false, 'count' => fn () => Feedback::count()],
                ],
            ],
            [
                'title' => 'Access & Security',
                'description' => 'Users, roles, tokens, and sessions.',
                'items' => [
                    ['key' => 'access_security_users', 'title' => 'Users', 'href' => route('admin.users.index'), 'icon' => 'Users', 'permission' => 'view_user', 'external' => false, 'count' => fn () => User::count()],
                    ['key' => 'access_security_roles', 'title' => 'Roles', 'href' => route('admin.roles.index'), 'icon' => 'ShieldCheck', 'permission' => 'view_role', 'external' => false, 'count' => null],
                    ['key' => 'access_security_api_tokens', 'title' => 'API Tokens', 'href' => route('admin.api_tokens.index'), 'icon' => 'Key', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'access_security_sessions', 'title' => 'Sessions', 'href' => route('admin.sessions.index'), 'icon' => 'MonitorSmartphone', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'access_security_custom_cards', 'title' => 'Custom Cards', 'href' => route('admin.custom_cards.index'), 'icon' => 'ShieldAlert', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                ],
            ],
            [
                'title' => 'Campaign — Advancement',
                'description' => 'Index of the Untold advancement charts.',
                'items' => [
                    ['key' => 'campaign_advancement_attack_mod', 'title' => 'Attack Mod', 'href' => route('admin.campaign.advancement-attack-mod.index'), 'icon' => 'Swords', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_advancement_tactical_mod', 'title' => 'Tactical Mod', 'href' => route('admin.campaign.advancement-tactical-mod.index'), 'icon' => 'Swords', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_advancement_action', 'title' => 'Action', 'href' => route('admin.campaign.advancement-action.index'), 'icon' => 'Swords', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_advancement_ability', 'title' => 'Ability', 'href' => route('admin.campaign.advancement-ability.index'), 'icon' => 'Shield', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_advancement_summoning', 'title' => 'Summoning', 'href' => route('admin.actions.index').'?game_mode=campaign&campaign_advancement_kind=summoning', 'icon' => 'Puzzle', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                ],
            ],
            [
                'title' => 'Campaign — Catalog',
                'description' => 'Campaign equipment, injuries, crew cards, and events.',
                'items' => [
                    ['key' => 'campaign_catalog_crew_card_effects', 'title' => 'Crew Card Effects', 'href' => route('admin.abilities.index').'?game_mode=campaign&crew_card=1', 'icon' => 'ArrowUpCircle', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_equipment', 'title' => 'Equipment', 'href' => route('admin.upgrades.index').'?game_mode=campaign&campaign_upgrade_kind=equipment', 'icon' => 'Package', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_injuries', 'title' => 'Injuries', 'href' => route('admin.upgrades.index').'?game_mode=campaign&campaign_upgrade_kind=injury', 'icon' => 'AlertTriangle', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_totem_templates', 'title' => 'Totem Templates', 'href' => route('admin.campaign.totem-templates.index'), 'icon' => 'Puzzle', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_campaign_crew_cards', 'title' => 'Campaign Crew Cards', 'href' => route('admin.campaign.crew-cards.index'), 'icon' => 'Newspaper', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_lucky_miss', 'title' => 'Lucky Miss', 'href' => route('admin.campaign.lucky-miss.index'), 'icon' => 'Shield', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_back_alley_doctor', 'title' => 'Back-Alley Doctor', 'href' => route('admin.campaign.back-alley-doctor.index'), 'icon' => 'Activity', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                    ['key' => 'campaign_catalog_weekly_events', 'title' => 'Weekly Events', 'href' => route('admin.campaign.weekly-events.index'), 'icon' => 'Newspaper', 'permission' => 'view_campaign_catalog', 'external' => false, 'count' => null],
                ],
            ],
            [
                'title' => 'TOS Catalog',
                'description' => 'Allegiances, units, cards, assets, and stratagems.',
                'items' => [
                    ['key' => 'tos_catalog_units', 'title' => 'Units', 'href' => route('admin.tos.units.index'), 'icon' => 'Swords', 'permission' => 'view_tos_unit', 'external' => false, 'count' => fn () => TosUnit::count()],
                    ['key' => 'tos_catalog_sculpts', 'title' => 'Sculpts', 'href' => route('admin.tos.sculpts.index'), 'icon' => 'FileImage', 'permission' => 'view_tos_sculpt', 'external' => false, 'count' => null],
                    ['key' => 'tos_catalog_special_rules', 'title' => 'Special Rules', 'href' => route('admin.tos.special_rules.index'), 'icon' => 'BookOpen', 'permission' => 'view_tos_special_unit_rule', 'external' => false, 'count' => fn () => TosSpecialUnitRule::count()],
                    ['key' => 'tos_catalog_abilities', 'title' => 'Abilities', 'href' => route('admin.tos.abilities.index'), 'icon' => 'Shield', 'permission' => 'view_tos_ability', 'external' => false, 'count' => fn () => TosAbility::count()],
                    ['key' => 'tos_catalog_actions', 'title' => 'Actions', 'href' => route('admin.tos.actions.index'), 'icon' => 'Swords', 'permission' => 'view_tos_action', 'external' => false, 'count' => null],
                    ['key' => 'tos_catalog_triggers', 'title' => 'Triggers', 'href' => route('admin.tos.triggers.index'), 'icon' => 'Swords', 'permission' => 'view_tos_trigger', 'external' => false, 'count' => null],
                    ['key' => 'tos_catalog_allegiances', 'title' => 'Allegiances', 'href' => route('admin.tos.allegiances.index'), 'icon' => 'Shield', 'permission' => 'view_tos_allegiance', 'external' => false, 'count' => fn () => TosAllegiance::count()],
                    ['key' => 'tos_catalog_allegiance_cards', 'title' => 'Allegiance Cards', 'href' => route('admin.tos.allegiance_cards.index'), 'icon' => 'BookOpen', 'permission' => 'view_tos_allegiance_card', 'external' => false, 'count' => fn () => TosAllegianceCard::count()],
                    ['key' => 'tos_catalog_assets', 'title' => 'Assets', 'href' => route('admin.tos.assets.index'), 'icon' => 'Package', 'permission' => 'view_tos_asset', 'external' => false, 'count' => fn () => TosAsset::count()],
                    ['key' => 'tos_catalog_stratagems', 'title' => 'Stratagems', 'href' => route('admin.tos.stratagems.index'), 'icon' => 'Newspaper', 'permission' => 'view_tos_stratagem', 'external' => false, 'count' => fn () => TosStratagem::count()],
                ],
            ],
            [
                'title' => 'Diagnostics',
                'description' => 'Read-only logs and observability tools.',
                'items' => [
                    ['key' => 'diagnostics_activity_log', 'title' => 'Activity Log', 'href' => route('admin.activity.index'), 'icon' => 'Activity', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'diagnostics_games', 'title' => 'Games', 'href' => route('admin.games.index'), 'icon' => 'Swords', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'diagnostics_failed_jobs', 'title' => 'Failed Jobs', 'href' => route('admin.failed_jobs.index'), 'icon' => 'AlertTriangle', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'diagnostics_image_health', 'title' => 'Image Health', 'href' => route('admin.image_health.index'), 'icon' => 'ImageOff', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'diagnostics_logs', 'title' => 'Logs', 'href' => '/log-viewer', 'icon' => 'FileText', 'permission' => 'super_admin', 'external' => true, 'count' => null],
                    ['key' => 'diagnostics_telescope', 'title' => 'Telescope', 'href' => '/telescope', 'icon' => 'Telescope', 'permission' => 'super_admin', 'external' => true, 'count' => null],
                ],
            ],
            [
                'title' => 'System Controls',
                'description' => 'Maintenance, caches, schedules, and flags.',
                'items' => [
                    ['key' => 'system_controls_maintenance', 'title' => 'Maintenance', 'href' => route('admin.maintenance.index'), 'icon' => 'ServerCrash', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'system_controls_cache_controls', 'title' => 'Cache Controls', 'href' => route('admin.cache.index'), 'icon' => 'Eraser', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'system_controls_schedule', 'title' => 'Schedule', 'href' => route('admin.schedule.index'), 'icon' => 'Clock', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'system_controls_trash', 'title' => 'Trash', 'href' => route('admin.trash.index').'super_admin'.'Feature Flags'.route('admin.features.index'), 'icon' => 'Flag', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                    ['key' => 'system_controls_tournament_override', 'title' => 'Tournament Override', 'href' => route('admin.tournaments.index'), 'icon' => 'Trophy', 'permission' => 'super_admin', 'external' => false, 'count' => null],
                ],
            ],
        ];
    }
}
