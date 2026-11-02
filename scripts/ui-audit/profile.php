<?php

/*
 * Query / payload profiler for the local audit database — see docs/ui-audit.md and docs/performance.md.
 * Local use only (it signs in as a seeded user without a password).
 *
 *   php scripts/ui-audit/profile.php <guest|admin|member> urls.txt [--props]
 *
 * `urls.txt` holds one path per line. For each it issues an in-process Inertia (XHR) GET and prints
 * the status, query count, the most-repeated query shape (`dup` ≥ 5 is the N+1 signature), DB and
 * wall time, and the props size. `--props` adds the five heaviest props per page.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if ($app->isProduction()) {
    fwrite(STDERR, "Refusing to run in production.\n");
    exit(1);
}

$role = $argv[1] ?? 'guest';
$file = $argv[2] ?? null;
$showProps = in_array('--props', $argv, true);
if (! $file || ! is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/ui-audit/profile.php <guest|admin|member> urls.txt [--props]\n");
    exit(1);
}

$emails = ['admin' => 'audit.admin@example.test', 'member' => 'audit.member@example.test'];
$user = isset($emails[$role]) ? App\Models\User::where('email', $emails[$role])->first() : null;
if (isset($emails[$role]) && ! $user) {
    fwrite(STDERR, "No {$emails[$role]} user — run UiAuditSeeder (docs/ui-audit.md).\n");
    exit(1);
}

$urls = array_values(array_filter(array_map('trim', file($file, FILE_IGNORE_NEW_LINES))));
$version = (string) $app->make(App\Http\Middleware\HandleInertiaRequests::class)->version(Request::create('/'));

$queries = [];
DB::listen(function ($query) use (&$queries) {
    $queries[] = [preg_replace('/\s+/', ' ', $query->sql), $query->time];
});

foreach ($urls as $url) {
    $queries = [];
    $app['auth']->forgetGuards();
    if ($user) {
        $app['auth']->guard('web')->setUser($user);
    }

    $request = Request::create($url, 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => $version,
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $started = microtime(true);
    try {
        $response = $kernel->handle($request);
    } catch (Throwable $e) {
        printf("%-55s EXC %s\n", $url, substr($e->getMessage(), 0, 90));

        continue;
    }
    $wall = (microtime(true) - $started) * 1000;

    $json = json_decode($response->getContent(), true);
    $props = is_array($json) && isset($json['props']) ? $json['props'] : null;
    $size = $props !== null ? strlen(json_encode($props)) : strlen($response->getContent());

    $shapes = [];
    foreach ($queries as [$sql]) {
        $shapes[$sql] = ($shapes[$sql] ?? 0) + 1;
    }
    arsort($shapes);
    $worst = array_key_first($shapes) ?? '';
    $repeats = $shapes[$worst] ?? 0;

    printf(
        "%-55s %3d q=%-4d dup=%-3d db=%6.0fms t=%6.0fms props=%7.1fKB\n",
        $url, $response->getStatusCode(), count($queries), $repeats, array_sum(array_column($queries, 1)), $wall, $size / 1024,
    );
    if ($repeats >= 5) {
        echo "      ↳ ×{$repeats} ".substr($worst, 0, 190)."\n";
    }
    if ($showProps && $props !== null) {
        $sizes = array_map(fn ($value) => strlen(json_encode($value)), $props);
        arsort($sizes);
        foreach (array_slice($sizes, 0, 5, true) as $key => $bytes) {
            printf("      %-30s %7.1fKB\n", $key, $bytes / 1024);
        }
    }

    $kernel->terminate($request, $response);
}
