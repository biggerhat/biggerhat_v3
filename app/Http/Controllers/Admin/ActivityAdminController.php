<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
                'log_names' => [],
                'events' => [],
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

        return inertia('Admin/Activity/Index', [
            'activities' => $activities,
            'filters' => [
                'log' => $logName,
                'event' => $event,
                'causer' => $causerId,
            ],
            'log_names' => Activity::query()->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name'),
            'events' => Activity::query()->select('event')->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'storage_ready' => true,
        ]);
    }
}
