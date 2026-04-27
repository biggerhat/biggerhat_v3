<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\LogsAdminActivity;
use App\Traits\LogsCreationActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Response;
use Inertia\ResponseFactory;
use Spatie\Activitylog\Models\Activity;

class ActivityAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        // Activitylog table is published with our migrations but might not be
        // run yet on a fresh checkout — short-circuit with an empty page rather
        // than 500 the admin out of their own diagnostic UI.
        if (! Schema::hasTable('activity_log')) {
            return inertia('Admin/Activity/Index', [
                'activities' => ['data' => [], 'links' => [], 'current_page' => 1, 'last_page' => 1, 'total' => 0],
                'filters' => ['log' => null, 'event' => null, 'causer' => null],
                'log_names' => self::registeredLogNames(),
                'events' => ['created', 'updated', 'deleted'],
                'storage_ready' => false,
            ]);
        }

        $logName = $request->string('log')->toString() ?: null;
        $event = $request->string('event')->toString() ?: null;
        $causerId = $request->integer('causer') ?: null;

        $query = Activity::query()
            ->with(['causer:id,name', 'subject'])
            ->latest();

        if ($logName) {
            $query->where('log_name', $logName);
        }
        if ($event) {
            $query->where('event', $event);
        }
        if ($causerId) {
            $query->where('causer_id', $causerId)->where('causer_type', \App\Models\User::class);
        }

        $activities = $query->paginate(50)->withQueryString();

        // Union registered models with names that have actually logged something —
        // shows the full list of tracked models even on a fresh install where
        // nothing has been written to activity_log yet.
        $stored = Activity::query()
            ->whereNotNull('log_name')
            ->select('log_name')
            ->distinct()
            ->pluck('log_name')
            ->all();

        $logNames = collect(self::registeredLogNames())
            ->merge($stored)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return inertia('Admin/Activity/Index', [
            'activities' => $activities,
            'filters' => [
                'log' => $logName,
                'event' => $event,
                'causer' => $causerId,
            ],
            'log_names' => $logNames,
            'events' => Activity::query()->select('event')->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'storage_ready' => true,
        ]);
    }

    /**
     * Discover every Eloquent model that uses one of our LogsActivity wrapper
     * traits and derive its log name (lowercased class basename, matching what
     * the traits set via `useLogName(...)`). Cached forever in the app cache
     * — model classes only change on deploys, and `cache:clear` runs on
     * every deploy.
     *
     * @return array<int, string>
     */
    private static function registeredLogNames(): array
    {
        // v2 cache key — switched to a recursive scan so models in nested
        // namespaces (e.g. App\Models\TOS\*) are picked up. Bumping the key
        // invalidates the v1 cache entry on first hit after deploy.
        return \Illuminate\Support\Facades\Cache::rememberForever('admin:activity:log_names:v2', function () {
            $names = [];
            $modelsRoot = app_path('Models');
            foreach (File::allFiles($modelsRoot) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = trim(substr($file->getPathname(), strlen($modelsRoot)), DIRECTORY_SEPARATOR);
                $relative = str_replace(['/', '\\'], '\\', $relative);
                $class = 'App\\Models\\'.preg_replace('/\.php$/', '', $relative);
                if (! is_string($class) || ! class_exists($class)) {
                    continue;
                }
                $traits = class_uses_recursive($class);
                if (in_array(LogsAdminActivity::class, $traits, true) || in_array(LogsCreationActivity::class, $traits, true)) {
                    $names[] = strtolower(class_basename($class));
                }
            }

            sort($names);

            return array_values(array_unique($names));
        });
    }
}
