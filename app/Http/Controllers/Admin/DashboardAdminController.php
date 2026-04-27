<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Feedback;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\TOS\Ability as TosAbility;
use App\Models\TOS\Allegiance as TosAllegiance;
use App\Models\TOS\AllegianceCard as TosAllegianceCard;
use App\Models\TOS\Asset as TosAsset;
use App\Models\TOS\Envoy as TosEnvoy;
use App\Models\TOS\SpecialUnitRule as TosSpecialUnitRule;
use App\Models\TOS\Stratagem as TosStratagem;
use App\Models\TOS\Unit as TosUnit;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;
use Inertia\ResponseFactory;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
use Throwable;

class DashboardAdminController extends Controller
{
    private const ANALYTICS_CACHE_KEY = 'admin:dashboard:analytics:v1';

    private const ANALYTICS_CACHE_MINUTES = 30;

    public function index(Request $request): Response|ResponseFactory|RedirectResponse
    {
        $user = $request->user();

        $groups = $this->groupsForUser($user);

        // Single-visible-group users go straight to that section. Only super_admins
        // and multi-permission staff stop on the dashboard.
        if (count($groups) === 1 && ! $user->hasRole('super_admin')) {
            $firstItem = $groups[0]['items'][0] ?? null;
            if ($firstItem) {
                return redirect($firstItem['href']);
            }
        }

        return inertia('Admin/Dashboard/Index', [
            'groups' => $groups,
            'stats' => $this->stats($user),
            'analytics' => $user->hasRole('super_admin') ? $this->analytics() : null,
        ]);
    }

    /**
     * @return array<int, array{title: string, description: string, items: array<int, array{label: string, href: string, count: int|null}>}>
     */
    private function groupsForUser($user): array
    {
        $groups = [
            [
                'title' => 'Game Data',
                'description' => 'Characters, rules, upgrades, and board pieces.',
                'items' => [
                    ['label' => 'Characters', 'permission' => 'view_character', 'href' => route('admin.characters.index'), 'count' => fn () => Character::count()],
                    ['label' => 'Miniatures', 'permission' => 'view_miniature', 'href' => route('admin.miniatures.index'), 'count' => fn () => Miniature::count()],
                    ['label' => 'Keywords', 'permission' => 'view_keyword', 'href' => route('admin.keywords.index'), 'count' => fn () => Keyword::count()],
                    ['label' => 'Upgrades', 'permission' => 'view_upgrade', 'href' => route('admin.upgrades.index'), 'count' => fn () => Upgrade::count()],
                ],
            ],
            [
                'title' => 'Content',
                'description' => 'Articles, lore, blueprints, and packages.',
                'items' => [
                    ['label' => 'Articles', 'permission' => 'create_posts|edit_posts', 'href' => route('admin.blog.posts.index'), 'count' => fn () => BlogPost::count()],
                    ['label' => 'Lore', 'permission' => 'view_lore', 'href' => route('admin.lores.index'), 'count' => null],
                    ['label' => 'Blueprints', 'permission' => 'view_blueprint', 'href' => route('admin.blueprints.index'), 'count' => null],
                ],
            ],
            [
                'title' => 'Community',
                'description' => 'Channels, feedback, and external links.',
                'items' => [
                    ['label' => 'Channels', 'permission' => 'view_channel', 'href' => route('admin.channels.index'), 'count' => null],
                    ['label' => 'Feedback', 'permission' => 'view_feedback', 'href' => route('admin.feedback.index'), 'count' => fn () => Feedback::count()],
                    ['label' => 'POD Links', 'permission' => 'view_pod_link', 'href' => route('admin.pod_links.index'), 'count' => null],
                ],
            ],
            [
                'title' => 'Access',
                'description' => 'Users and roles.',
                'items' => [
                    ['label' => 'Users', 'permission' => 'view_user', 'href' => route('admin.users.index'), 'count' => fn () => User::count()],
                    ['label' => 'Roles', 'permission' => 'view_role', 'href' => route('admin.roles.index'), 'count' => null],
                ],
            ],
            [
                'title' => 'The Other Side',
                'description' => 'Allegiances, units, cards, envoys, assets, and stratagems.',
                'items' => [
                    ['label' => 'Allegiances', 'permission' => 'view_tos_allegiance', 'href' => route('admin.tos.allegiances.index'), 'count' => fn () => TosAllegiance::count()],
                    ['label' => 'Allegiance Cards', 'permission' => 'view_tos_allegiance_card', 'href' => route('admin.tos.allegiance_cards.index'), 'count' => fn () => TosAllegianceCard::count()],
                    ['label' => 'Envoys', 'permission' => 'view_tos_envoy', 'href' => route('admin.tos.envoys.index'), 'count' => fn () => TosEnvoy::count()],
                    ['label' => 'Units', 'permission' => 'view_tos_unit', 'href' => route('admin.tos.units.index'), 'count' => fn () => TosUnit::count()],
                    ['label' => 'Special Rules', 'permission' => 'view_tos_special_unit_rule', 'href' => route('admin.tos.special_rules.index'), 'count' => fn () => TosSpecialUnitRule::count()],
                    ['label' => 'Abilities', 'permission' => 'view_tos_ability', 'href' => route('admin.tos.abilities.index'), 'count' => fn () => TosAbility::count()],
                    ['label' => 'Assets', 'permission' => 'view_tos_asset', 'href' => route('admin.tos.assets.index'), 'count' => fn () => TosAsset::count()],
                    ['label' => 'Stratagems', 'permission' => 'view_tos_stratagem', 'href' => route('admin.tos.stratagems.index'), 'count' => fn () => TosStratagem::count()],
                ],
            ],
        ];

        $result = [];
        foreach ($groups as $group) {
            $visibleItems = [];
            foreach ($group['items'] as $item) {
                if (! $this->userHasAnyPermission($user, $item['permission'])) {
                    continue;
                }
                $visibleItems[] = [
                    'label' => $item['label'],
                    'href' => $item['href'],
                    'count' => is_callable($item['count']) ? ($item['count'])() : null,
                ];
            }
            if (count($visibleItems) > 0) {
                $result[] = [
                    'title' => $group['title'],
                    'description' => $group['description'],
                    'items' => $visibleItems,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{pending_feedback: int|null}
     */
    private function stats($user): array
    {
        return [
            'pending_feedback' => $user->can('view_feedback')
                ? Feedback::where('status', FeedbackStatusEnum::New->value)->count()
                : null,
        ];
    }

    /**
     * Cache wrapper for the GA4 fetches. Spatie's own cache is configured to
     * 0 minutes (delegated to us) so we can attach a fetched_at stamp and
     * support a manual refresh button. 30-minute TTL is a balance between
     * "Saturday traffic visible by Sunday morning" and "don't hammer GA4".
     *
     * @return array{summary: array{visitors: int, totalUsers: int, pageViews: int, sessions: int}|null, today: array{visitors: int, pageViews: int}|null, topPages: array<int, array{pageTitle: string, pagePath: string, screenPageViews: int}>|null, chart: array<int, array{date: string, visitors: int, pageViews: int}>|null, error: string|null, fetched_at: string|null}
     */
    private function analytics(): array
    {
        return Cache::remember(self::ANALYTICS_CACHE_KEY, now()->addMinutes(self::ANALYTICS_CACHE_MINUTES), fn () => $this->fetchAnalyticsFresh());
    }

    /**
     * Manually clear the analytics cache + redirect back to the dashboard, so
     * the next render re-fetches from GA4. Used by the "Refresh" button.
     */
    public function refreshAnalytics(Request $request): RedirectResponse
    {
        Cache::forget(self::ANALYTICS_CACHE_KEY);

        return redirect()->route('admin.dashboard')->withMessage('Analytics refreshed from Google Analytics.');
    }

    /**
     * @return array{summary: array{visitors: int, totalUsers: int, pageViews: int, sessions: int}|null, today: array{visitors: int, pageViews: int}|null, topPages: array<int, array{pageTitle: string, pagePath: string, screenPageViews: int}>|null, chart: array<int, array{date: string, visitors: int, pageViews: int}>|null, error: string|null, fetched_at: string|null}
     */
    private function fetchAnalyticsFresh(): array
    {
        if (! config('analytics.property_id')) {
            return ['summary' => null, 'today' => null, 'topPages' => null, 'chart' => null, 'error' => 'Analytics not configured.', 'fetched_at' => null];
        }

        try {
            // 7-day totals via a *single* direct query with no date dimension —
            // this matches what GA4's UI shows for the same period (deduped
            // active users, total users, sessions, page views). Summing the
            // per-day series double-counts users who visit on multiple days,
            // and was also dropping days that GA4 returned no rows for.
            $summaryRow = Analytics::get(
                period: Period::days(7),
                metrics: ['activeUsers', 'totalUsers', 'screenPageViews', 'sessions'],
                dimensions: [],
                maxResults: 1,
            )->first();

            $summary = [
                'visitors' => (int) ($summaryRow['activeUsers'] ?? 0),
                'totalUsers' => (int) ($summaryRow['totalUsers'] ?? 0),
                'pageViews' => (int) ($summaryRow['screenPageViews'] ?? 0),
                'sessions' => (int) ($summaryRow['sessions'] ?? 0),
            ];

            // "Today" is its own widget so the admin can quickly see whether
            // GA4 is processing live traffic. GA4 has a few-hour data delay
            // for standard reports, so this is the canary.
            $todayRow = Analytics::get(
                period: Period::days(1),
                metrics: ['activeUsers', 'screenPageViews'],
                dimensions: [],
                maxResults: 1,
            )->first();

            $today = [
                'visitors' => (int) ($todayRow['activeUsers'] ?? 0),
                'pageViews' => (int) ($todayRow['screenPageViews'] ?? 0),
            ];

            // 30-day daily series for the sparkline. keepEmptyRows so days
            // with zero traffic still produce a row → no horizontal compression
            // when low-traffic days exist.
            $dailyTotals = Analytics::get(
                period: Period::days(30),
                metrics: ['activeUsers', 'screenPageViews'],
                dimensions: ['date'],
                maxResults: 31,
                keepEmptyRows: true,
            )
                ->sortBy(fn ($row) => $row['date']->format('Y-m-d'))
                ->values();

            $chart = $dailyTotals
                ->map(fn ($row) => [
                    'date' => $row['date']->format('Y-m-d'),
                    'visitors' => (int) $row['activeUsers'],
                    'pageViews' => (int) $row['screenPageViews'],
                ])
                ->values()
                ->all();

            $topPages = Analytics::fetchMostVisitedPages(Period::days(7), 10)
                ->map(fn ($row) => [
                    'pageTitle' => $row['pageTitle'],
                    'pagePath' => $row['fullPageUrl'],
                    'screenPageViews' => $row['screenPageViews'],
                ])
                ->values()
                ->all();

            return [
                'summary' => $summary,
                'today' => $today,
                'topPages' => $topPages,
                'chart' => $chart,
                'error' => null,
                'fetched_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            return [
                'summary' => null,
                'today' => null,
                'topPages' => null,
                'chart' => null,
                'error' => $e->getMessage(),
                'fetched_at' => now()->toIso8601String(),
            ];
        }
    }

    private function userHasAnyPermission($user, string $permission): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        foreach (explode('|', $permission) as $p) {
            if ($user->can($p)) {
                return true;
            }
        }

        return false;
    }
}
