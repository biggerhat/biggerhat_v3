<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Response;
use Inertia\ResponseFactory;

class ImageHealthAdminController extends Controller
{
    /** Tables + columns we audit. Add new image columns here as the schema grows. */
    private const TARGETS = [
        ['table' => 'tos_allegiances', 'columns' => ['logo_path']],
        ['table' => 'tos_allegiance_cards', 'columns' => ['image_path']],
        ['table' => 'tos_envoys', 'columns' => ['image_path']],
        ['table' => 'tos_assets', 'columns' => ['image_path']],
        ['table' => 'tos_stratagems', 'columns' => ['image_path']],
        ['table' => 'blog_posts', 'columns' => ['featured_image']],
        ['table' => 'miniatures', 'columns' => ['front_image', 'back_image', 'combination_image']],
        ['table' => 'packages', 'columns' => ['front_image', 'back_image', 'combination_image']],
        ['table' => 'upgrades', 'columns' => ['front_image', 'back_image', 'combination_image']],
        ['table' => 'schemes', 'columns' => ['image']],
        ['table' => 'channels', 'columns' => ['image']],
        // custom_characters had image columns dropped on 2026-04-14 — kept off
        // the list intentionally. The Schema::hasColumn defense in scan()
        // still protects the scan if a future column gets dropped silently.
        ['table' => 'blueprint_images', 'columns' => ['path']],
    ];

    private const CACHE_KEY = 'admin:image-health';

    private const CACHE_TTL_MINUTES = 60;

    public function index(Request $request): Response|ResponseFactory
    {
        $report = Cache::get(self::CACHE_KEY);

        return inertia('Admin/ImageHealth/Index', [
            'report' => $report,
        ]);
    }

    public function scan(Request $request): RedirectResponse
    {
        $missing = [];
        $checked = 0;
        $brokenCount = 0;
        $skippedColumns = [];

        foreach (self::TARGETS as $target) {
            $table = $target['table'];

            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            // Filter out columns that have been dropped since the targets list
            // was written (e.g. custom_characters had its image columns dropped
            // on 2026-04-14). Without this defense the SELECT errors out and
            // the whole scan dies on one stale entry.
            $existingColumns = array_values(array_filter(
                $target['columns'],
                fn (string $col) => \Illuminate\Support\Facades\Schema::hasColumn($table, $col),
            ));

            foreach (array_diff($target['columns'], $existingColumns) as $missingCol) {
                $skippedColumns[] = "{$table}.{$missingCol}";
            }

            if (count($existingColumns) === 0) {
                continue;
            }

            $select = ['id', ...$existingColumns];
            // Some tables are bigger than others — chunk so we don't load the
            // entire image-bearing universe into memory at once.
            DB::table($table)->select($select)->orderBy('id')->chunk(500, function ($rows) use (&$missing, &$checked, &$brokenCount, $table, $existingColumns) {
                foreach ($rows as $row) {
                    foreach ($existingColumns as $col) {
                        $path = $row->{$col} ?? null;
                        if (! $path) {
                            continue;
                        }
                        $checked++;
                        if (! self::pathExists($path)) {
                            $brokenCount++;
                            $missing[] = [
                                'table' => $table,
                                'column' => $col,
                                'id' => $row->id,
                                'path' => $path,
                            ];
                        }
                    }
                }
            });
        }

        $report = [
            'scanned_at' => now()->toIso8601String(),
            'checked_count' => $checked,
            'broken_count' => $brokenCount,
            'missing' => $missing,
            'skipped_columns' => array_values(array_unique($skippedColumns)),
        ];

        Cache::put(self::CACHE_KEY, $report, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return redirect()->route('admin.image_health.index')
            ->withMessage("Scan complete — checked {$checked} paths, found {$brokenCount} broken.");
    }

    /**
     * Check whether an image path is reachable. Handles three storage shapes
     * we use: absolute URLs (HTTP), public-rooted paths ("/images/foo.png"),
     * and storage-disk-relative paths ("tos/allegiances/foo.png").
     */
    private static function pathExists(string $path): bool
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            // Don't HTTP-probe external URLs in a sync request — assume valid.
            return true;
        }

        if (str_starts_with($path, '/')) {
            return File::exists(public_path(ltrim($path, '/')));
        }

        // Disk-relative — assume the public disk (storage/app/public).
        return File::exists(storage_path('app/public/'.$path));
    }
}
