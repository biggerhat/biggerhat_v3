<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Response;
use Inertia\ResponseFactory;
use Throwable;

class CacheAdminController extends Controller
{
    private const COMMANDS = [
        'cache' => ['command' => 'cache:clear', 'label' => 'Application cache', 'description' => 'Clears the framework cache (Cache facade).'],
        'view' => ['command' => 'view:clear', 'label' => 'Compiled views', 'description' => 'Removes compiled Blade templates.'],
        'route' => ['command' => 'route:clear', 'label' => 'Route cache', 'description' => 'Drops the cached route registrar.'],
        'config' => ['command' => 'config:clear', 'label' => 'Config cache', 'description' => 'Drops the cached merged config.'],
        'event' => ['command' => 'event:clear', 'label' => 'Event cache', 'description' => 'Drops the cached event/listener map.'],
        'optimize' => ['command' => 'optimize:clear', 'label' => 'Everything', 'description' => 'Calls every clear command at once.'],
    ];

    public function index(Request $request): Response|ResponseFactory
    {
        return inertia('Admin/Cache/Index', [
            'commands' => collect(self::COMMANDS)->map(fn ($cfg, $key) => [
                'key' => $key,
                'command' => $cfg['command'],
                'label' => $cfg['label'],
                'description' => $cfg['description'],
            ])->values(),
        ]);
    }

    public function clear(Request $request, string $key): RedirectResponse
    {
        $config = self::COMMANDS[$key] ?? null;
        if (! $config) {
            return back()->withMessage('Unknown cache target.');
        }

        try {
            Artisan::call($config['command']);

            return back()->withMessage("Cleared: {$config['label']}.");
        } catch (Throwable $e) {
            return back()->withMessage("Clear failed: {$e->getMessage()}");
        }
    }
}
