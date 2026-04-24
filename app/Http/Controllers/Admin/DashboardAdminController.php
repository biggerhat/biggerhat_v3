<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Character;
use App\Models\Feedback;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\TOS\Allegiance as TosAllegiance;
use App\Models\TOS\Asset as TosAsset;
use App\Models\TOS\Stratagem as TosStratagem;
use App\Models\TOS\Unit as TosUnit;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
use Throwable;

class DashboardAdminController extends Controller
{
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
                    ['label' => 'Units', 'permission' => 'view_tos_unit', 'href' => route('admin.tos.units.index'), 'count' => fn () => TosUnit::count()],
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
     * @return array{summary: array{visitors: int, pageViews: int}|null, topPages: array<int, array{pageTitle: string, pagePath: string, screenPageViews: int}>|null, chart: array<int, array{date: string, visitors: int, pageViews: int}>|null, error: string|null}
     */
    private function analytics(): array
    {
        if (! config('analytics.property_id')) {
            return ['summary' => null, 'topPages' => null, 'chart' => null, 'error' => 'Analytics not configured.'];
        }

        try {
            // 30 days of daily totals, oldest-first for the chart.
            $dailyTotals = Analytics::fetchTotalVisitorsAndPageViews(Period::days(30), 30)
                ->sortBy(fn ($row) => $row['date']->format('Y-m-d'))
                ->values();

            // Last 7 days = last 7 entries of the daily series.
            $last7 = $dailyTotals->slice(-7);
            $summary = [
                'visitors' => (int) $last7->sum('activeUsers'),
                'pageViews' => (int) $last7->sum('screenPageViews'),
            ];

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

            return ['summary' => $summary, 'topPages' => $topPages, 'chart' => $chart, 'error' => null];
        } catch (Throwable $e) {
            return ['summary' => null, 'topPages' => null, 'chart' => null, 'error' => $e->getMessage()];
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
