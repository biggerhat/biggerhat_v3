<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Providers\FeatureFlagsServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Pennant\Feature;

class FeatureFlagsAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $tableExists = Schema::hasTable(config('pennant.stores.database.table', 'features'));

        $flags = collect(FeatureFlagsServiceProvider::FLAGS)->map(function (array $meta, string $name) {
            return [
                'name' => $name,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'default' => $meta['default'],
                'active' => FeatureFlagsServiceProvider::resolveGlobal($name, $meta['default']),
                'has_override' => FeatureFlagsServiceProvider::hasGlobalOverride($name),
            ];
        })->values();

        return inertia('Admin/Features/Index', [
            'flags' => $flags,
            'storage_ready' => $tableExists,
        ]);
    }

    public function update(Request $request, string $name): RedirectResponse
    {
        if (! array_key_exists($name, FeatureFlagsServiceProvider::FLAGS)) {
            return back()->withMessage('Unknown feature flag.');
        }

        $action = $request->string('action')->toString();

        // Pennant's database driver stores rows keyed by feature + scope, where
        // null scope is our "global override" channel. activate/deactivate write
        // a true/false; clear() removes the override and falls back to the
        // registry default.
        match ($action) {
            'activate' => Feature::for(null)->activate($name),
            'deactivate' => Feature::for(null)->deactivate($name),
            'clear' => Feature::for(null)->forget($name),
            default => null,
        };

        return back()->withMessage("Feature {$name} {$action}d.");
    }
}
