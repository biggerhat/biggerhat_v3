<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;

class MaintenanceAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $isDown = app()->isDownForMaintenance();
        $payload = $isDown ? $this->readDownPayload() : null;

        return inertia('Admin/Maintenance/Index', [
            'is_down' => $isDown,
            'down_payload' => $payload,
            // The bypass URL the admin can use to keep themselves in while
            // everyone else is locked out.
            'bypass_url' => $payload && isset($payload['secret'])
                ? url('/'.$payload['secret'])
                : null,
        ]);
    }

    public function down(Request $request): RedirectResponse
    {
        $secret = Str::random(20);
        Artisan::call('down', [
            '--secret' => $secret,
            '--render' => 'errors::503',
        ]);

        // Redirect THROUGH the bypass URL — Laravel's PreventRequestsDuringMaintenance
        // middleware intercepts /{secret} and sets a bypass cookie before forwarding
        // the user on. Without this, the admin is immediately locked out by the 503
        // page they just enabled, and never sees the secret to use later.
        return redirect()->to('/'.$secret);
    }

    public function up(Request $request): RedirectResponse
    {
        Artisan::call('up');

        return redirect()->route('admin.maintenance.index')->withMessage('Site is back up.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readDownPayload(): ?array
    {
        $path = storage_path('framework/down');
        if (! file_exists($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            return null;
        }
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }
}
