<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\BotHeartbeat;
use App\Models\Feedback;
use App\Models\User;
use App\Support\AdminNav;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Response;
use Inertia\ResponseFactory;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
use Throwable;

class DashboardAdminController extends Controller
{
    private const ANALYTICS_CACHE_KEY = 'admin:dashboard:analytics:v1';

    private const ANALYTICS_CACHE_MINUTES = 30;

    private const COUNT_CACHE_MINUTES = 5;

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
            'stats' => $stats = $this->stats($user),
            'attention' => $this->attention($user, $stats),
            'analytics' => $user->hasRole('super_admin') ? $this->analytics() : null,
        ]);
    }

    /**
     * Dashboard tiles: the shared admin nav (App\Support\AdminNav), with a
     * cached row count on the items that define one.
     *
     * @return array<int, array{title: string, description: string, items: array<int, array{label: string, href: string, count: int|null}>}>
     */
    private function groupsForUser(User $user): array
    {
        $result = [];
        foreach (AdminNav::definition() as $group) {
            $visibleItems = [];
            foreach ($group['items'] as $item) {
                if ($item['external'] || ! AdminNav::allows($user, $item['permission'])) {
                    continue;
                }
                $visibleItems[] = [
                    'label' => $item['title'],
                    'href' => $item['href'],
                    // Counts are cosmetic — a short cache avoids a COUNT(*) per item per visit.
                    'count' => $item['count'] !== null
                        ? Cache::remember(
                            'admin:dashboard:count:'.$item['key'],
                            now()->addMinutes(self::COUNT_CACHE_MINUTES),
                            $item['count'],
                        )
                        : null,
                ];
            }
            if ($visibleItems !== []) {
                $result[] = ['title' => $group['title'], 'description' => $group['description'], 'items' => $visibleItems];
            }
        }

        return $result;
    }

    /**
     * Things an admin should act on, most urgent first. Empty means all clear.
     *
     * @param  array{pending_feedback: int|null, failed_jobs: int|null}  $stats
     * @return array<int, array{key: string, level: 'critical'|'warning'|'info', title: string, detail: string, href: string, action: string}>
     */
    private function attention(User $user, array $stats): array
    {
        $items = [];

        if ($user->hasRole('super_admin')) {
            $last = BotHeartbeat::query()->latest('reported_at')->first();
            $minutes = max(1, (int) config('services.bot.offline_after_minutes', 3));
            if ($last && $last->reported_at->lt(now()->subMinutes($minutes))) {
                $items[] = [
                    'key' => 'bot_offline',
                    'level' => 'critical',
                    'title' => 'Discord bot is offline',
                    'detail' => 'Last heartbeat '.$last->reported_at->diffForHumans().'.',
                    'href' => route('admin.bot_monitor.index'),
                    'action' => 'Open Bot Monitor',
                ];
            }
        }

        if (($stats['failed_jobs'] ?? 0) > 0) {
            $items[] = [
                'key' => 'failed_jobs',
                'level' => 'warning',
                'title' => $stats['failed_jobs'].' failed queue '.($stats['failed_jobs'] === 1 ? 'job' : 'jobs'),
                'detail' => 'Review the errors, then retry or delete them.',
                'href' => route('admin.failed_jobs.index'),
                'action' => 'Review jobs',
            ];
        }

        if (($stats['pending_feedback'] ?? 0) > 0) {
            $items[] = [
                'key' => 'pending_feedback',
                'level' => 'info',
                'title' => $stats['pending_feedback'].' new feedback '.($stats['pending_feedback'] === 1 ? 'entry' : 'entries'),
                'detail' => 'Waiting for a first look.',
                'href' => route('admin.feedback.index', ['status' => 'new']),
                'action' => 'Review feedback',
            ];
        }

        return $items;
    }

    /**
     * @return array{pending_feedback: int|null, failed_jobs: int|null}
     */
    private function stats($user): array
    {
        return [
            'pending_feedback' => $user->can('view_feedback')
                ? Feedback::where('status', FeedbackStatusEnum::New->value)->count()
                : null,
            // Ops signal for super admins only (failed-jobs tool is super_admin gated).
            'failed_jobs' => $user->hasRole('super_admin') && Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
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
        $cached = Cache::get(self::ANALYTICS_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $fresh = $this->fetchAnalyticsFresh();

        // A failed fetch is only held briefly so a transient GA4 error doesn't
        // blank the analytics panel for the full TTL.
        Cache::put(
            self::ANALYTICS_CACHE_KEY,
            $fresh,
            $fresh['error'] !== null ? now()->addMinute() : now()->addMinutes(self::ANALYTICS_CACHE_MINUTES),
        );

        return $fresh;
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
}
